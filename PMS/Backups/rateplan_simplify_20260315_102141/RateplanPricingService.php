<?php

class RateplanPricingService
{
    /** @var PDO */
    private $pdo;

    /** @var array<string,array<int,array<string,mixed>>> */
    private $modifiersByRateplan = array();
    /** @var array<int,array<int,array<string,mixed>>> */
    private $schedulesByModifier = array();
    /** @var array<int,array<int,array<string,mixed>>> */
    private $conditionsByModifier = array();
    /** @var array<int,array<int,array<string,mixed>>> */
    private $scopesByModifier = array();
    /** @var array<string,int> */
    private $roomsTotalCache = array();
    /** @var array<string,int> */
    private $roomsSoldCache = array();
    /** @var array<string,?int> */
    private $overrideCache = array();
    /** @var array<string,?array<string,mixed>> */
    private $legacyClampCache = array();

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getNightlyPriceCents(
        $idRateplan,
        $idProperty,
        $date,
        $idCategory = null,
        $idRoom = null,
        $channel = null,
        $arrivalDate = null,
        $nights = null
    ) {
        $row = $this->getNightlyBreakdown($idRateplan, $idProperty, $date, $idCategory, $idRoom, array(
            'channel' => $channel,
            'arrival_date' => $arrivalDate,
            'nights' => $nights
        ));
        return isset($row['final_price_cents']) ? (int)$row['final_price_cents'] : 0;
    }

    public function getNightlyBreakdown(
        $idRateplan,
        $idProperty,
        $date,
        $idCategory = null,
        $idRoom = null,
        array $context = array()
    ) {
        $idRateplan = (int)$idRateplan;
        $idProperty = (int)$idProperty;
        $date = $this->dateYmd($date);
        $idCategory = $idCategory !== null ? (int)$idCategory : 0;
        $idRoom = $idRoom !== null ? (int)$idRoom : 0;

        if ($date === null || $idProperty <= 0) {
            throw new RuntimeException('Invalid pricing arguments.');
        }

        if ($idCategory <= 0 && $idRoom > 0) {
            $idCategory = $this->fetchOneInt(
                'SELECT id_category FROM room WHERE id_room = ? AND deleted_at IS NULL LIMIT 1',
                array($idRoom)
            );
        }
        if ($idCategory <= 0) {
            throw new RuntimeException('Category is required.');
        }
        if ($idRateplan <= 0) {
            $idRateplan = $this->resolveRateplanFromScope($idProperty, $idCategory, $idRoom);
        }

        $cat = $this->fetchOneRow(
            'SELECT default_base_price_cents, min_price_cents FROM roomcategory WHERE id_category = ? AND deleted_at IS NULL LIMIT 1',
            array($idCategory)
        );
        $base = $cat ? (int)$cat['default_base_price_cents'] : 0;
        $categoryMin = $cat && (int)$cat['min_price_cents'] > 0 ? (int)$cat['min_price_cents'] : $base;

        $occupancyProperty = $this->occupancyPct($idProperty, $date, 0, $context);
        $occupancyCategory = $this->occupancyPct($idProperty, $date, $idCategory, $context);
        $channel = isset($context['channel']) ? strtolower(trim((string)$context['channel'])) : '';
        $nights = isset($context['nights']) ? (int)$context['nights'] : null;
        $arrival = isset($context['arrival_date']) ? $this->dateYmd($context['arrival_date']) : null;
        $daysToArrival = ($arrival !== null) ? (int)floor((strtotime($date) - strtotime($arrival)) / 86400) : null;
        $dow = $this->dowCode($date);

        $price = $base;
        $beforeModifiers = $price;
        $applied = array();

        $mods = $idRateplan > 0 ? $this->applicableModifiers($idRateplan, $date, $idCategory, $idRoom) : array();
        if ($mods) {
            $groups = array();
            foreach ($mods as $m) {
                $p = isset($m['priority']) ? (int)$m['priority'] : 0;
                if (!isset($groups[$p])) {
                    $groups[$p] = array();
                }
                $groups[$p][] = $m;
            }
            krsort($groups, SORT_NUMERIC);

            foreach ($groups as $priority => $group) {
                $groupBase = $price;
                $stack = array();
                $bestGuest = array();
                $bestProperty = array();
                $overrides = array();
                foreach ($group as $m) {
                    $mode = isset($m['apply_mode']) ? (string)$m['apply_mode'] : 'stack';
                    if ($mode === 'best_for_guest') {
                        $bestGuest[] = $m;
                    } elseif ($mode === 'best_for_property') {
                        $bestProperty[] = $m;
                    } elseif ($mode === 'override') {
                        $overrides[] = $m;
                    } else {
                        $stack[] = $m;
                    }
                }

                foreach ($stack as $m) {
                    if (!$this->conditionsPass($m, $date, $idProperty, $idCategory, $idRoom, $occupancyProperty, $occupancyCategory, $daysToArrival, $nights, $channel, $dow)) {
                        continue;
                    }
                    $before = $price;
                    $price = $this->applyModifier($price, $m, $categoryMin);
                    $applied[] = $this->appliedRow($m, $priority, $before, $price);
                }

                $pick = $this->pickBestModifier($bestGuest, true, $groupBase, $date, $idProperty, $idCategory, $idRoom, $occupancyProperty, $occupancyCategory, $daysToArrival, $nights, $channel, $dow, $categoryMin);
                if ($pick !== null) {
                    $before = $price;
                    $price = (int)$pick['after'];
                    $applied[] = $this->appliedRow($pick['modifier'], $priority, $before, $price);
                }

                $pick = $this->pickBestModifier($bestProperty, false, $groupBase, $date, $idProperty, $idCategory, $idRoom, $occupancyProperty, $occupancyCategory, $daysToArrival, $nights, $channel, $dow, $categoryMin);
                if ($pick !== null) {
                    $before = $price;
                    $price = (int)$pick['after'];
                    $applied[] = $this->appliedRow($pick['modifier'], $priority, $before, $price);
                }

                foreach ($overrides as $m) {
                    if (!$this->conditionsPass($m, $date, $idProperty, $idCategory, $idRoom, $occupancyProperty, $occupancyCategory, $daysToArrival, $nights, $channel, $dow)) {
                        continue;
                    }
                    $before = $price;
                    $price = $this->applyModifier($price, $m, $categoryMin);
                    $applied[] = $this->appliedRow($m, $priority, $before, $price);
                }
            }
        }

        $legacyClamp = $this->legacyClamp($idRateplan);
        if ($legacyClamp !== null) {
            if (isset($legacyClamp['max_discount_pct']) && $legacyClamp['max_discount_pct'] !== null) {
                $price = max($price, (int)round($base * (1 - ((float)$legacyClamp['max_discount_pct'] / 100.0))));
            }
            if (isset($legacyClamp['max_markup_pct']) && $legacyClamp['max_markup_pct'] !== null) {
                $price = min($price, (int)round($base * (1 + ((float)$legacyClamp['max_markup_pct'] / 100.0))));
            }
        }

        $price = max($price, $categoryMin);
        $override = $this->overridePrice($idRateplan, $date, $idCategory, $idRoom);
        $final = $override !== null ? (int)$override : (int)$price;

        return array(
            'calendar_date' => $date,
            'base_cents' => (int)$base,
            'min_cents' => (int)$categoryMin,
            'base_adjust_pct' => $this->sumPctByName($applied, 'base'),
            'base_adjusted_cents' => (int)round($base * (1 + ($this->sumPctByName($applied, 'base') / 100.0))),
            'season_adjust_pct' => $this->sumPctByName($applied, 'season'),
            'occupancy_pct' => (float)$occupancyProperty,
            'occupancy_adjust_pct' => $this->sumPctByName($applied, 'occup'),
            'override_price_cents' => $override !== null ? (int)$override : null,
            'price_before_modifiers_cents' => (int)$beforeModifiers,
            'final_price_cents' => (int)$final,
            'applied_modifiers' => $applied
        );
    }

    public function getCalendarPrices(
        $idRateplan,
        $idProperty,
        $dateFrom,
        $dateTo,
        $idCategory = null,
        $idRoom = null,
        array $context = array()
    ) {
        $from = $this->dateYmd($dateFrom);
        $to = $this->dateYmd($dateTo);
        if ($from === null || $to === null) {
            throw new RuntimeException('Invalid date range.');
        }
        if ($from > $to) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }

        $rows = array();
        $cursor = $from;
        while ($cursor <= $to) {
            $rows[] = $this->getNightlyBreakdown($idRateplan, $idProperty, $cursor, $idCategory, $idRoom, $context);
            $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
        }
        return $rows;
    }

    public function getCalendarPricesByCodes($propertyCode, $rateplanCode, $categoryCode, $roomCode, $fromDate, $days, array $context = array())
    {
        $propertyCode = strtoupper(trim((string)$propertyCode));
        $rateplanCode = strtoupper(trim((string)$rateplanCode));
        $categoryCode = strtoupper(trim((string)$categoryCode));
        $roomCode = trim((string)$roomCode);
        $from = $this->dateYmd($fromDate);
        $days = max(1, min(120, (int)$days));

        if ($propertyCode === '' || $from === null) {
            throw new RuntimeException('Property and start date are required.');
        }

        $idProperty = $this->fetchOneInt('SELECT id_property FROM property WHERE code = ? AND deleted_at IS NULL LIMIT 1', array($propertyCode));
        if ($idProperty <= 0) {
            throw new RuntimeException('Unknown property.');
        }

        $idRoom = 0;
        $idCategory = 0;
        if ($roomCode !== '') {
            $room = $this->fetchOneRow(
                'SELECT id_room, id_category FROM room WHERE id_property = ? AND code = ? AND deleted_at IS NULL LIMIT 1',
                array($idProperty, $roomCode)
            );
            if (!$room) {
                throw new RuntimeException('Unknown room.');
            }
            $idRoom = (int)$room['id_room'];
            $idCategory = (int)$room['id_category'];
        } else {
            if ($categoryCode === '') {
                throw new RuntimeException('Category or room is required.');
            }
            $idCategory = $this->fetchOneInt(
                'SELECT id_category FROM roomcategory WHERE id_property = ? AND code = ? AND deleted_at IS NULL LIMIT 1',
                array($idProperty, $categoryCode)
            );
            if ($idCategory <= 0) {
                throw new RuntimeException('Unknown category.');
            }
        }

        $idRateplan = 0;
        if ($rateplanCode !== '') {
            $idRateplan = $this->fetchOneInt(
                'SELECT id_rateplan FROM rateplan WHERE id_property = ? AND code = ? AND deleted_at IS NULL LIMIT 1',
                array($idProperty, $rateplanCode)
            );
        }
        if ($idRateplan <= 0) {
            $idRateplan = $this->resolveRateplanFromScope($idProperty, $idCategory, $idRoom);
        }

        $rows = array();
        for ($i = 0; $i < $days; $i++) {
            $d = date('Y-m-d', strtotime($from . ' +' . $i . ' day'));
            $row = $this->getNightlyBreakdown($idRateplan, $idProperty, $d, $idCategory, $idRoom > 0 ? $idRoom : null, $context);
            $row['day_index'] = $i;
            $rows[] = $row;
        }
        return $rows;
    }

    private function resolveRateplanFromScope($idProperty, $idCategory, $idRoom)
    {
        $idProperty = (int)$idProperty;
        if ((int)$idRoom > 0) {
            $id = $this->fetchOneInt(
                'SELECT COALESCE(r.id_rateplan, rc.id_rateplan) FROM room r LEFT JOIN roomcategory rc ON rc.id_category = r.id_category WHERE r.id_room = ? AND r.id_property = ? AND r.deleted_at IS NULL LIMIT 1',
                array((int)$idRoom, $idProperty)
            );
            if ($id > 0) {
                return $id;
            }
        }
        return $this->fetchOneInt(
            'SELECT id_rateplan FROM roomcategory WHERE id_category = ? AND id_property = ? AND deleted_at IS NULL LIMIT 1',
            array((int)$idCategory, $idProperty)
        );
    }

    private function applicableModifiers($idRateplan, $date, $idCategory, $idRoom)
    {
        $idRateplan = (int)$idRateplan;
        if (!isset($this->modifiersByRateplan[$idRateplan])) {
            $mods = $this->fetchAll(
                'SELECT * FROM rateplan_modifier WHERE id_rateplan = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY priority DESC, id_rateplan_modifier ASC',
                array($idRateplan)
            );
            $this->modifiersByRateplan[$idRateplan] = $mods;
            $ids = array();
            foreach ($mods as $m) {
                $ids[] = (int)$m['id_rateplan_modifier'];
            }
            $this->loadModifierChildren($ids);
        }

        $out = array();
        foreach ($this->modifiersByRateplan[$idRateplan] as $m) {
            $idModifier = (int)$m['id_rateplan_modifier'];
            if (!$this->scopePass($idModifier, $idCategory, $idRoom)) {
                continue;
            }
            if (!$this->schedulePass($m, $date)) {
                continue;
            }
            $out[] = $m;
        }
        return $out;
    }

    private function loadModifierChildren(array $modifierIds)
    {
        $modifierIds = array_values(array_unique(array_filter(array_map('intval', $modifierIds))));
        if (!$modifierIds) {
            return;
        }
        $in = implode(',', array_fill(0, count($modifierIds), '?'));

        $rows = $this->fetchAll(
            'SELECT * FROM rateplan_modifier_schedule WHERE id_rateplan_modifier IN (' . $in . ') AND is_active = 1 AND deleted_at IS NULL ORDER BY id_rateplan_modifier_schedule',
            $modifierIds
        );
        foreach ($rows as $row) {
            $id = (int)$row['id_rateplan_modifier'];
            if (!isset($this->schedulesByModifier[$id])) {
                $this->schedulesByModifier[$id] = array();
            }
            $this->schedulesByModifier[$id][] = $row;
        }

        $rows = $this->fetchAll(
            'SELECT * FROM rateplan_modifier_condition WHERE id_rateplan_modifier IN (' . $in . ') AND is_active = 1 AND deleted_at IS NULL ORDER BY sort_order, id_rateplan_modifier_condition',
            $modifierIds
        );
        foreach ($rows as $row) {
            $id = (int)$row['id_rateplan_modifier'];
            if (!isset($this->conditionsByModifier[$id])) {
                $this->conditionsByModifier[$id] = array();
            }
            $this->conditionsByModifier[$id][] = $row;
        }

        $rows = $this->fetchAll(
            'SELECT * FROM rateplan_modifier_scope WHERE id_rateplan_modifier IN (' . $in . ') AND is_active = 1 AND deleted_at IS NULL ORDER BY id_rateplan_modifier_scope',
            $modifierIds
        );
        foreach ($rows as $row) {
            $id = (int)$row['id_rateplan_modifier'];
            if (!isset($this->scopesByModifier[$id])) {
                $this->scopesByModifier[$id] = array();
            }
            $this->scopesByModifier[$id][] = $row;
        }

        foreach ($modifierIds as $id) {
            if (!isset($this->schedulesByModifier[$id])) {
                $this->schedulesByModifier[$id] = array();
            }
            if (!isset($this->conditionsByModifier[$id])) {
                $this->conditionsByModifier[$id] = array();
            }
            if (!isset($this->scopesByModifier[$id])) {
                $this->scopesByModifier[$id] = array();
            }
        }
    }

    private function scopePass($idModifier, $idCategory, $idRoom)
    {
        $scopes = isset($this->scopesByModifier[(int)$idModifier]) ? $this->scopesByModifier[(int)$idModifier] : array();
        if (!$scopes) {
            return true;
        }
        foreach ($scopes as $s) {
            $c = isset($s['id_category']) ? (int)$s['id_category'] : 0;
            $r = isset($s['id_room']) ? (int)$s['id_room'] : 0;
            if ($c <= 0 && $r <= 0) return true;
            if ($r > 0 && $idRoom > 0 && $r === $idRoom) return true;
            if ($c > 0 && $idCategory > 0 && $c === $idCategory) return true;
        }
        return false;
    }

    private function schedulePass(array $modifier, $date)
    {
        $idModifier = (int)$modifier['id_rateplan_modifier'];
        $schedules = isset($this->schedulesByModifier[$idModifier]) ? $this->schedulesByModifier[$idModifier] : array();
        $alwaysOn = isset($modifier['is_always_on']) && (int)$modifier['is_always_on'] === 1;
        if ($alwaysOn && !$schedules) return true;
        if (!$schedules) return false;
        foreach ($schedules as $s) {
            if ($this->dateInSchedule($date, $s)) return true;
        }
        return false;
    }

    private function dateInSchedule($date, array $schedule)
    {
        $exdates = $this->jsonArray(isset($schedule['exdates_json']) ? $schedule['exdates_json'] : null);
        if ($exdates && in_array($date, $exdates, true)) return false;

        $type = isset($schedule['schedule_type']) ? (string)$schedule['schedule_type'] : 'range';
        if ($type === 'range') {
            $start = $this->dateYmd(isset($schedule['start_date']) ? $schedule['start_date'] : null);
            $end = $this->dateYmd(isset($schedule['end_date']) ? $schedule['end_date'] : null);
            if ($start !== null && $date < $start) return false;
            if ($end !== null && $date > $end) return false;
            return true;
        }
        return $this->dateInRrule($date, isset($schedule['schedule_rrule']) ? (string)$schedule['schedule_rrule'] : '', isset($schedule['start_date']) ? $schedule['start_date'] : null, isset($schedule['end_date']) ? $schedule['end_date'] : null);
    }

    private function dateInRrule($date, $rrule, $startDate, $endDate)
    {
        $start = $this->dateYmd($startDate);
        if ($start === null) $start = $date;
        $end = $this->dateYmd($endDate);
        if ($date < $start) return false;
        if ($end !== null && $date > $end) return false;

        $parts = $this->parseRrule($rrule);
        $freq = isset($parts['FREQ']) ? strtoupper($parts['FREQ']) : 'WEEKLY';
        $interval = isset($parts['INTERVAL']) ? max(1, (int)$parts['INTERVAL']) : 1;
        $until = isset($parts['UNTIL']) ? $this->dateYmd($parts['UNTIL']) : null;
        if ($until !== null && $date > $until) return false;

        if ($freq === 'WEEKLY') {
            $days = isset($parts['BYDAY']) ? array_filter(array_map('trim', explode(',', $parts['BYDAY']))) : array();
            if (!$days) $days = array($this->dowCode($start));
            if (!in_array($this->dowCode($date), $days, true)) return false;
            $weeks = (int)floor((strtotime($date) - strtotime($start)) / 86400 / 7);
            return ($weeks % $interval) === 0;
        }
        if ($freq === 'MONTHLY') {
            $monthDiff = ((int)date('Y', strtotime($date)) - (int)date('Y', strtotime($start))) * 12
                + ((int)date('n', strtotime($date)) - (int)date('n', strtotime($start)));
            if ($monthDiff < 0 || ($monthDiff % $interval) !== 0) return false;
            $days = isset($parts['BYMONTHDAY']) ? array_filter(array_map('intval', explode(',', $parts['BYMONTHDAY']))) : array((int)date('j', strtotime($start)));
            return in_array((int)date('j', strtotime($date)), $days, true);
        }
        if ($freq === 'YEARLY') {
            $yearDiff = (int)date('Y', strtotime($date)) - (int)date('Y', strtotime($start));
            if ($yearDiff < 0 || ($yearDiff % $interval) !== 0) return false;
            $months = isset($parts['BYMONTH']) ? array_filter(array_map('intval', explode(',', $parts['BYMONTH']))) : array();
            $days = isset($parts['BYMONTHDAY']) ? array_filter(array_map('intval', explode(',', $parts['BYMONTHDAY']))) : array();
            if ($months && !in_array((int)date('n', strtotime($date)), $months, true)) return false;
            if ($days && !in_array((int)date('j', strtotime($date)), $days, true)) return false;
            return true;
        }
        return false;
    }

    private function parseRrule($rrule)
    {
        $out = array();
        $rrule = trim((string)$rrule);
        if ($rrule === '') return $out;
        foreach (explode(';', $rrule) as $part) {
            $kv = explode('=', $part, 2);
            if (count($kv) !== 2) continue;
            $out[strtoupper(trim($kv[0]))] = trim($kv[1]);
        }
        return $out;
    }

    private function conditionsPass(array $modifier, $date, $idProperty, $idCategory, $idRoom, $occupancyProperty, $occupancyCategory, $daysToArrival, $nights, $channel, $dow)
    {
        $conds = isset($this->conditionsByModifier[(int)$modifier['id_rateplan_modifier']]) ? $this->conditionsByModifier[(int)$modifier['id_rateplan_modifier']] : array();
        if (!$conds) return true;
        foreach ($conds as $c) {
            $type = strtolower(trim((string)$c['condition_type']));
            $op = strtolower(trim((string)$c['operator_key']));
            $v1 = isset($c['value_number']) ? (float)$c['value_number'] : null;
            $v2 = isset($c['value_number_to']) ? (float)$c['value_number_to'] : null;
            $text = isset($c['value_text']) ? (string)$c['value_text'] : '';
            $json = isset($c['value_json']) ? $c['value_json'] : null;
            $actual = null;
            if ($type === 'occupancy_pct_property') $actual = (float)$occupancyProperty;
            elseif ($type === 'occupancy_pct_category') $actual = (float)$occupancyCategory;
            elseif ($type === 'days_to_arrival') $actual = $daysToArrival;
            elseif ($type === 'min_los') { $actual = $nights; $op = 'gte'; }
            elseif ($type === 'max_los') { $actual = $nights; $op = 'lte'; }
            elseif ($type === 'dow_in') { $actual = strtoupper($dow); $op = 'in'; }
            elseif ($type === 'channel_in') { $actual = strtolower($channel); $op = 'in'; }
            elseif ($type === 'pickup_reservations') {
                $cfg = $this->jsonObject($json);
                $lookback = isset($cfg['lookback_days']) ? max(1, (int)$cfg['lookback_days']) : 7;
                $actual = (float)$this->pickupReservations($idProperty, $idCategory, $idRoom, $date, $lookback);
            } else {
                continue;
            }
            if (!$this->compare($actual, $op, $v1, $v2, $text, $json)) return false;
        }
        return true;
    }

    private function compare($actual, $op, $v1, $v2, $text, $json)
    {
        if ($op === 'lt') return $actual !== null && $v1 !== null && (float)$actual < (float)$v1;
        if ($op === 'lte') return $actual !== null && $v1 !== null && (float)$actual <= (float)$v1;
        if ($op === 'gt') return $actual !== null && $v1 !== null && (float)$actual > (float)$v1;
        if ($op === 'gte') return $actual !== null && $v1 !== null && (float)$actual >= (float)$v1;
        if ($op === 'between') return $actual !== null && $v1 !== null && $v2 !== null && (float)$actual >= (float)$v1 && (float)$actual <= (float)$v2;
        if ($op === 'eq') return (string)$actual === ((string)$text !== '' ? (string)$text : (string)$v1);
        if ($op === 'neq') return (string)$actual !== ((string)$text !== '' ? (string)$text : (string)$v1);
        if ($op === 'in') {
            $values = trim((string)$text) !== '' ? array_map('trim', explode(',', (string)$text)) : $this->jsonArray($json);
            $norm = array(); foreach ($values as $v) { $v = strtolower(trim((string)$v)); if ($v !== '') $norm[] = $v; }
            return in_array(strtolower(trim((string)$actual)), $norm, true);
        }
        return true;
    }

    private function pickBestModifier(array $mods, $preferLower, $groupBase, $date, $idProperty, $idCategory, $idRoom, $occupancyProperty, $occupancyCategory, $daysToArrival, $nights, $channel, $dow, $categoryMin)
    {
        $chosen = null;
        foreach ($mods as $m) {
            if (!$this->conditionsPass($m, $date, $idProperty, $idCategory, $idRoom, $occupancyProperty, $occupancyCategory, $daysToArrival, $nights, $channel, $dow)) continue;
            $after = $this->applyModifier((int)$groupBase, $m, (int)$categoryMin);
            if ($chosen === null) { $chosen = array('modifier' => $m, 'after' => $after); continue; }
            if ($preferLower && $after < $chosen['after']) $chosen = array('modifier' => $m, 'after' => $after);
            if (!$preferLower && $after > $chosen['after']) $chosen = array('modifier' => $m, 'after' => $after);
        }
        return $chosen;
    }

    private function applyModifier($price, array $m, $categoryMin)
    {
        $price = (int)$price;
        $action = isset($m['price_action']) ? (string)$m['price_action'] : 'add_pct';
        if ($action === 'set_price') $price = isset($m['set_price_cents']) ? (int)$m['set_price_cents'] : $price;
        elseif ($action === 'add_cents') $price += isset($m['add_cents']) ? (int)$m['add_cents'] : 0;
        else { $pct = isset($m['add_pct']) ? (float)$m['add_pct'] : 0.0; $price = (int)round($price * (1 + ($pct / 100.0))); }
        if (isset($m['clamp_min_cents']) && $m['clamp_min_cents'] !== null) $price = max($price, (int)$m['clamp_min_cents']);
        if (isset($m['clamp_max_cents']) && $m['clamp_max_cents'] !== null) $price = min($price, (int)$m['clamp_max_cents']);
        if (isset($m['respect_category_min']) && (int)$m['respect_category_min'] === 1) $price = max($price, (int)$categoryMin);
        return $price;
    }

    private function appliedRow(array $m, $priority, $before, $after)
    {
        return array(
            'id_rateplan_modifier' => (int)$m['id_rateplan_modifier'],
            'modifier_name' => isset($m['modifier_name']) ? (string)$m['modifier_name'] : '',
            'priority' => (int)$priority,
            'apply_mode' => isset($m['apply_mode']) ? (string)$m['apply_mode'] : 'stack',
            'price_action' => isset($m['price_action']) ? (string)$m['price_action'] : 'add_pct',
            'add_pct' => isset($m['add_pct']) ? (float)$m['add_pct'] : null,
            'before_cents' => (int)$before,
            'after_cents' => (int)$after
        );
    }

    private function sumPctByName(array $applied, $needle)
    {
        $needle = strtolower((string)$needle);
        $sum = 0.0;
        foreach ($applied as $row) {
            if (!isset($row['price_action']) || $row['price_action'] !== 'add_pct') continue;
            $name = strtolower(isset($row['modifier_name']) ? (string)$row['modifier_name'] : '');
            if (strpos($name, $needle) !== false) $sum += isset($row['add_pct']) ? (float)$row['add_pct'] : 0.0;
        }
        return $sum;
    }

    private function occupancyPct($idProperty, $date, $idCategory, array $context)
    {
        $total = $this->roomsTotal($idProperty, $idCategory);
        if ($total <= 0) return 0.0;
        $sold = $this->roomsSold($idProperty, $date, $idCategory, $context);
        return round(($sold / $total) * 100, 2);
    }

    private function roomsTotal($idProperty, $idCategory)
    {
        $key = (int)$idProperty . '|' . (int)$idCategory;
        if (isset($this->roomsTotalCache[$key])) return $this->roomsTotalCache[$key];
        if ((int)$idCategory > 0) {
            $total = $this->fetchOneInt('SELECT COUNT(*) FROM room WHERE id_property = ? AND id_category = ? AND is_active = 1 AND deleted_at IS NULL', array((int)$idProperty, (int)$idCategory));
        } else {
            $total = $this->fetchOneInt('SELECT COUNT(*) FROM room WHERE id_property = ? AND is_active = 1 AND deleted_at IS NULL', array((int)$idProperty));
        }
        $this->roomsTotalCache[$key] = (int)$total;
        return $this->roomsTotalCache[$key];
    }

    private function roomsSold($idProperty, $date, $idCategory, array $context)
    {
        $countApartado = !empty($context['count_apartado']);
        $key = (int)$idProperty . '|' . $date . '|' . (int)$idCategory . '|' . ($countApartado ? '1' : '0');
        if (isset($this->roomsSoldCache[$key])) return $this->roomsSoldCache[$key];

        $statuses = $countApartado ? array('confirmado', 'en casa', 'salida', 'apartado') : array('confirmado', 'en casa', 'salida');
        $statusIn = implode(',', array_fill(0, count($statuses), '?'));

        if ((int)$idCategory > 0) {
            $sql = 'SELECT COUNT(DISTINCT x.room_id) FROM (
                      SELECT r.id_room AS room_id
                      FROM reservation res
                      JOIN room r ON r.id_room = res.id_room
                      WHERE res.id_property = ?
                        AND res.deleted_at IS NULL
                        AND COALESCE(res.is_active,1)=1
                        AND LOWER(TRIM(COALESCE(res.status,"confirmado"))) IN (' . $statusIn . ')
                        AND res.check_in_date <= ?
                        AND res.check_out_date > ?
                        AND r.deleted_at IS NULL
                        AND r.id_category = ?
                      UNION ALL
                      SELECT rb.id_room
                      FROM room_block rb
                      JOIN room r ON r.id_room = rb.id_room
                      WHERE rb.id_property = ?
                        AND rb.deleted_at IS NULL
                        AND rb.is_active = 1
                        AND rb.start_date <= ?
                        AND rb.end_date > ?
                        AND r.deleted_at IS NULL
                        AND r.id_category = ?
                    ) x';
            $params = array((int)$idProperty);
            foreach ($statuses as $s) $params[] = $s;
            $params[] = $date; $params[] = $date; $params[] = (int)$idCategory;
            $params[] = (int)$idProperty; $params[] = $date; $params[] = $date; $params[] = (int)$idCategory;
        } else {
            $sql = 'SELECT COUNT(DISTINCT x.room_id) FROM (
                      SELECT res.id_room AS room_id
                      FROM reservation res
                      WHERE res.id_property = ?
                        AND res.id_room IS NOT NULL
                        AND res.deleted_at IS NULL
                        AND COALESCE(res.is_active,1)=1
                        AND LOWER(TRIM(COALESCE(res.status,"confirmado"))) IN (' . $statusIn . ')
                        AND res.check_in_date <= ?
                        AND res.check_out_date > ?
                      UNION ALL
                      SELECT rb.id_room
                      FROM room_block rb
                      WHERE rb.id_property = ?
                        AND rb.deleted_at IS NULL
                        AND rb.is_active = 1
                        AND rb.start_date <= ?
                        AND rb.end_date > ?
                    ) x';
            $params = array((int)$idProperty);
            foreach ($statuses as $s) $params[] = $s;
            $params[] = $date; $params[] = $date;
            $params[] = (int)$idProperty; $params[] = $date; $params[] = $date;
        }

        $sold = $this->fetchOneInt($sql, $params);
        $this->roomsSoldCache[$key] = (int)$sold;
        return $this->roomsSoldCache[$key];
    }

    private function pickupReservations($idProperty, $idCategory, $idRoom, $date, $lookbackDays)
    {
        $lookbackDays = max(1, (int)$lookbackDays);
        $from = date('Y-m-d 00:00:00', strtotime($date . ' -' . $lookbackDays . ' day'));
        $sql = 'SELECT COUNT(*)
                FROM reservation
                WHERE id_property = ?
                  AND deleted_at IS NULL
                  AND COALESCE(is_active,1)=1
                  AND LOWER(TRIM(COALESCE(status,"confirmado"))) IN ("confirmado","en casa","salida","apartado")
                  AND created_at >= ?
                  AND check_in_date <= ?
                  AND check_out_date > ?';
        $params = array((int)$idProperty, $from, $date, $date);
        if ((int)$idRoom > 0) {
            $sql .= ' AND id_room = ?';
            $params[] = (int)$idRoom;
        } elseif ((int)$idCategory > 0) {
            $sql .= ' AND id_category = ?';
            $params[] = (int)$idCategory;
        }
        return (int)$this->fetchOneInt($sql, $params);
    }

    private function overridePrice($idRateplan, $date, $idCategory, $idRoom)
    {
        $idRateplan = (int)$idRateplan;
        if ($idRateplan <= 0) return null;
        $key = $idRateplan . '|' . $date . '|' . (int)$idCategory . '|' . (int)$idRoom;
        if (array_key_exists($key, $this->overrideCache)) return $this->overrideCache[$key];

        $sql = 'SELECT price_cents
                FROM rateplan_override
                WHERE id_rateplan = ?
                  AND is_active = 1
                  AND override_date = ?
                  AND (
                    (? > 0 AND id_room = ?)
                    OR (? > 0 AND id_room IS NULL AND id_category = ?)
                    OR (id_room IS NULL AND id_category IS NULL)
                  )
                ORDER BY
                  CASE WHEN id_room IS NOT NULL THEN 0 WHEN id_category IS NOT NULL THEN 1 ELSE 2 END,
                  id_rateplan_override DESC
                LIMIT 1';
        $value = $this->fetchOneInt($sql, array($idRateplan, $date, (int)$idRoom, (int)$idRoom, (int)$idCategory, (int)$idCategory), true);
        $this->overrideCache[$key] = $value;
        return $value;
    }

    private function legacyClamp($idRateplan)
    {
        $idRateplan = (int)$idRateplan;
        if ($idRateplan <= 0) return null;
        $key = (string)$idRateplan;
        if (array_key_exists($key, $this->legacyClampCache)) return $this->legacyClampCache[$key];

        $row = $this->fetchOneRow('SELECT max_discount_pct, max_markup_pct, is_active FROM rateplan_pricing WHERE id_rateplan = ? LIMIT 1', array($idRateplan));
        if (!$row || (int)$row['is_active'] !== 1) {
            $this->legacyClampCache[$key] = null;
            return null;
        }
        $this->legacyClampCache[$key] = array(
            'max_discount_pct' => $row['max_discount_pct'] !== null ? (float)$row['max_discount_pct'] : null,
            'max_markup_pct' => $row['max_markup_pct'] !== null ? (float)$row['max_markup_pct'] : null
        );
        return $this->legacyClampCache[$key];
    }

    private function dowCode($date)
    {
        $w = (int)date('N', strtotime($date));
        $map = array(1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU');
        return isset($map[$w]) ? $map[$w] : 'MO';
    }

    private function dateYmd($value)
    {
        if ($value === null) return null;
        $text = trim((string)$value);
        if ($text === '') return null;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) return $text;
        $ts = strtotime($text);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    private function jsonArray($value)
    {
        if ($value === null) return array();
        $text = trim((string)$value);
        if ($text === '') return array();
        $d = json_decode($text, true);
        return is_array($d) ? $d : array();
    }

    private function jsonObject($value)
    {
        $d = $this->jsonArray($value);
        return is_array($d) ? $d : array();
    }

    private function fetchOneRow($sql, array $params = array())
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function fetchAll($sql, array $params = array())
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: array();
    }

    private function fetchOneInt($sql, array $params = array(), $allowNull = false)
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null) return $allowNull ? null : 0;
        return (int)$value;
    }
}
