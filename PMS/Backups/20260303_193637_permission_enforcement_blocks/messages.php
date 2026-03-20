<?php
$currentUser = pms_current_user();
$companyId = isset($currentUser['company_id']) ? (int)$currentUser['company_id'] : 0;
$companyCode = isset($currentUser['company_code']) ? $currentUser['company_code'] : '';
$actorUserId = isset($currentUser['id_user']) ? (int)$currentUser['id_user'] : null;

if ($companyId === 0 || $companyCode === '') {
    echo '<p class="error">No ha sido posible determinar la empresa actual.</p>';
    return;
}

$moduleKey = 'messages';
$properties = pms_fetch_properties($companyId);
$propertyIndex = array();
foreach ($properties as $property) {
    if (isset($property['code'], $property['id_property'])) {
        $propertyIndex[$property['code']] = (int)$property['id_property'];
    }
}

$selectedProperty = isset($_POST['messages_filter_property']) ? (string)$_POST['messages_filter_property'] : '';
$selectedPropertyId = isset($propertyIndex[$selectedProperty]) ? $propertyIndex[$selectedProperty] : null;

$message = null;
$error = null;
$activeTab = isset($_POST['messages_active_tab']) ? (string)$_POST['messages_active_tab'] : 'messages-tab-send';
$editTemplate = null;

$action = isset($_POST['messages_action']) ? (string)$_POST['messages_action'] : '';
if ($action === 'save_template') {
    $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    $propertyCode = isset($_POST['template_property_code']) ? trim((string)$_POST['template_property_code']) : '';
    $templateCode = isset($_POST['template_code']) ? trim((string)$_POST['template_code']) : '';
    $templateTitle = isset($_POST['template_title']) ? trim((string)$_POST['template_title']) : '';
    $templateBody = isset($_POST['template_body']) ? trim((string)$_POST['template_body']) : '';
    $templateActive = isset($_POST['template_is_active']) ? 1 : 0;

    if ($templateCode === '' || $templateTitle === '' || $templateBody === '') {
        $error = 'Codigo, titulo y cuerpo son obligatorios.';
    } else {
        try {
            pms_call_procedure('sp_message_template_upsert', array(
                $companyCode,
                $propertyCode === '' ? null : $propertyCode,
                $templateCode,
                $templateTitle,
                $templateBody,
                $templateActive,
                $templateId > 0 ? $templateId : null
            ));
            $message = 'Plantilla guardada.';
            $activeTab = 'messages-tab-templates';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
} elseif ($action === 'edit_template') {
    $templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
    if ($templateId > 0) {
        $activeTab = 'messages-tab-templates';
        $editTemplate = array('id_message_template' => $templateId);
    }
} elseif ($action === 'send_message') {
    $reservationId = isset($_POST['message_reservation_id']) ? (int)$_POST['message_reservation_id'] : 0;
    $templateId = isset($_POST['message_template_id']) ? (int)$_POST['message_template_id'] : 0;
    $messageTitle = isset($_POST['message_title']) ? trim((string)$_POST['message_title']) : '';
    $messageBody = isset($_POST['message_body']) ? trim((string)$_POST['message_body']) : '';
    $messagePhone = isset($_POST['message_phone']) ? preg_replace('/\D+/', '', (string)$_POST['message_phone']) : '';

    if ($reservationId <= 0 || $templateId <= 0) {
        $error = 'Selecciona reservacion y plantilla.';
    } elseif ($messageBody === '' || $messageTitle === '') {
        $error = 'El mensaje no puede estar vacio.';
    } elseif ($messagePhone === '') {
        $error = 'El telefono del huesped es obligatorio.';
    } else {
        try {
            pms_call_procedure('sp_reservation_message_send', array(
                $companyCode,
                $reservationId,
                $templateId,
                $actorUserId,
                $messagePhone,
                $messageTitle,
                $messageBody,
                'whatsapp'
            ));
            $message = 'Mensaje registrado como enviado.';
            $activeTab = 'messages-tab-send';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

$templates = array();
try {
    $pdo = pms_get_connection();
    $templateSql = 'SELECT mt.id_message_template,
                           mt.code,
                           mt.title,
                           mt.body,
                           mt.is_active,
                           mt.id_property,
                           p.code AS property_code,
                           p.name AS property_name
                    FROM message_template mt
                    LEFT JOIN property p ON p.id_property = mt.id_property
                    WHERE mt.id_company = ?
                      AND mt.deleted_at IS NULL
                    ORDER BY mt.title';
    $stmt = $pdo->prepare($templateSql);
    $stmt->execute(array($companyId));
    $templates = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
}

if ($editTemplate && isset($editTemplate['id_message_template'])) {
    foreach ($templates as $tpl) {
        if ((int)$tpl['id_message_template'] === (int)$editTemplate['id_message_template']) {
            $editTemplate = $tpl;
            break;
        }
    }
    if (is_array($editTemplate) && !isset($editTemplate['code'])) {
        $editTemplate = null;
    }
}

$activeTemplates = array_values(array_filter($templates, function ($tpl) use ($selectedProperty) {
    if (!isset($tpl['is_active']) || (int)$tpl['is_active'] !== 1) {
        return false;
    }
    if ($selectedProperty === '') {
        return true;
    }
    $templateProperty = isset($tpl['property_code']) ? (string)$tpl['property_code'] : '';
    return $templateProperty === '' || $templateProperty === $selectedProperty;
}));

$reservationOptions = array();
$selectedReservationId = isset($_POST['message_reservation_id']) ? (int)$_POST['message_reservation_id'] : 0;
try {
    $pdo = pms_get_connection();
    $params = array($companyId);
    $propertyFilterSql = '';
    if ($selectedProperty !== '') {
        $propertyFilterSql = ' AND p.code = ?';
        $params[] = $selectedProperty;
    }
    $stmt = $pdo->prepare(
        "SELECT r.id_reservation,
                r.code AS reservation_code,
                r.check_in_date,
                r.check_out_date,
                g.names AS guest_names,
                g.last_name AS guest_last_name,
                g.phone AS guest_phone,
                p.code AS property_code,
                p.name AS property_name,
                rm.code AS room_code,
                rc.name AS category_name
         FROM reservation r
         JOIN property p ON p.id_property = r.id_property
         LEFT JOIN guest g ON g.id_guest = r.id_guest
         LEFT JOIN room rm ON rm.id_room = r.id_room
         LEFT JOIN roomcategory rc ON rc.id_category = r.id_category
         WHERE p.id_company = ?
           AND r.deleted_at IS NULL
           AND COALESCE(r.status, '') NOT IN ('cancelled','canceled','cancelado','cancelada')" . $propertyFilterSql . "
         ORDER BY r.check_in_date DESC, r.id_reservation DESC
         LIMIT 300"
    );
    $stmt->execute($params);
    $reservationOptions = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $error ? $error : $e->getMessage();
}

$sentTemplates = array();
$sentLogRows = array();
if ($selectedReservationId > 0) {
    try {
        $stmt = $pdo->prepare(
            'SELECT rml.id_message_template,
                    rml.sent_at,
                    mt.code AS template_code,
                    mt.title AS template_title
             FROM reservation_message_log rml
             JOIN message_template mt ON mt.id_message_template = rml.id_message_template
             WHERE rml.id_reservation = ?
             ORDER BY rml.sent_at DESC'
        );
        $stmt->execute(array($selectedReservationId));
        $sentLogRows = $stmt->fetchAll();
        foreach ($sentLogRows as $row) {
            $sentTemplates[(int)$row['id_message_template']] = true;
        }
    } catch (Exception $e) {
        $error = $error ? $error : $e->getMessage();
    }
}
?>

<div class="reservation-tabs message-tabs" data-reservation-tabs="messages">
  <div class="reservation-tab-nav">
    <button type="button" class="reservation-tab-trigger <?php echo $activeTab === 'messages-tab-send' ? 'is-active' : ''; ?>" data-tab-target="messages-tab-send">Envio</button>
    <button type="button" class="reservation-tab-trigger <?php echo $activeTab === 'messages-tab-templates' ? 'is-active' : ''; ?>" data-tab-target="messages-tab-templates">Plantillas</button>
  </div>

  <?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php elseif ($message): ?>
    <p class="success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
  <?php endif; ?>

  <div class="reservation-tab-panel <?php echo $activeTab === 'messages-tab-send' ? 'is-active' : ''; ?>" id="messages-tab-send" data-tab-panel>
    <section class="card message-send-card">
      <div class="message-send-header">
        <div>
          <h2>Enviar mensaje por WhatsApp</h2>
          <p class="muted">Selecciona una reservacion y plantilla para enviar en un clic.</p>
        </div>
        <form method="post" class="form-inline">
          <input type="hidden" name="messages_active_tab" value="messages-tab-send">
          <label>
            Propiedad
            <select name="messages_filter_property" onchange="this.form.submit()">
              <option value="" <?php echo $selectedProperty === '' ? 'selected' : ''; ?>>Todas</option>
              <?php foreach ($properties as $property):
                $code = isset($property['code']) ? (string)$property['code'] : '';
                $name = isset($property['name']) ? (string)$property['name'] : '';
              ?>
                <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $code === $selectedProperty ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        </form>
      </div>

      <form method="post" id="message-send-form" class="form-grid grid-2">
        <input type="hidden" name="messages_action" value="send_message">
        <input type="hidden" name="messages_active_tab" value="messages-tab-send">
        <input type="hidden" name="messages_filter_property" value="<?php echo htmlspecialchars($selectedProperty, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="message_title" id="message-title-input">
        <input type="hidden" name="message_body" id="message-body-input">
        <input type="hidden" name="message_phone" id="message-phone-input">

        <label class="full">
          Buscar reservacion (huesped o telefono)
          <input type="text" id="message-reservation-search" placeholder="Ej. Ana Perez o 5551234567">
        </label>
        <label class="full">
          Reservacion
          <select name="message_reservation_id" id="message-reservation-select">
            <option value="">Selecciona una reservacion</option>
            <?php foreach ($reservationOptions as $reservation):
              $reservationId = isset($reservation['id_reservation']) ? (int)$reservation['id_reservation'] : 0;
              $reservationCode = isset($reservation['reservation_code']) ? (string)$reservation['reservation_code'] : '';
              $guestName = trim((isset($reservation['guest_names']) ? $reservation['guest_names'] : '') . ' ' . (isset($reservation['guest_last_name']) ? $reservation['guest_last_name'] : ''));
              $guestPhone = isset($reservation['guest_phone']) ? (string)$reservation['guest_phone'] : '';
              $propertyName = isset($reservation['property_name']) ? (string)$reservation['property_name'] : '';
              $propertyCode = isset($reservation['property_code']) ? (string)$reservation['property_code'] : '';
              $roomCode = isset($reservation['room_code']) ? (string)$reservation['room_code'] : '';
              $categoryName = isset($reservation['category_name']) ? (string)$reservation['category_name'] : '';
              $checkIn = isset($reservation['check_in_date']) ? (string)$reservation['check_in_date'] : '';
              $checkOut = isset($reservation['check_out_date']) ? (string)$reservation['check_out_date'] : '';
              $labelParts = array_filter(array(
                $reservationCode,
                $guestName !== '' ? $guestName : null,
                $propertyCode !== '' ? $propertyCode : null,
                $checkIn !== '' && $checkOut !== '' ? ($checkIn . ' / ' . $checkOut) : null
              ));
              $searchText = strtolower(trim($reservationCode . ' ' . $guestName . ' ' . $guestPhone));
              $selected = $selectedReservationId === $reservationId;
            ?>
              <option value="<?php echo $reservationId; ?>"
                data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>"
                data-guest-name="<?php echo htmlspecialchars($guestName, ENT_QUOTES, 'UTF-8'); ?>"
                data-guest-phone="<?php echo htmlspecialchars($guestPhone, ENT_QUOTES, 'UTF-8'); ?>"
                data-property-name="<?php echo htmlspecialchars($propertyName, ENT_QUOTES, 'UTF-8'); ?>"
                data-property-code="<?php echo htmlspecialchars($propertyCode, ENT_QUOTES, 'UTF-8'); ?>"
                data-check-in="<?php echo htmlspecialchars($checkIn, ENT_QUOTES, 'UTF-8'); ?>"
                data-check-out="<?php echo htmlspecialchars($checkOut, ENT_QUOTES, 'UTF-8'); ?>"
                data-room-code="<?php echo htmlspecialchars($roomCode, ENT_QUOTES, 'UTF-8'); ?>"
                data-category-name="<?php echo htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8'); ?>"
                data-reservation-code="<?php echo htmlspecialchars($reservationCode, ENT_QUOTES, 'UTF-8'); ?>"
                <?php echo $selected ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars(implode(' - ', $labelParts), ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="full">
          Plantilla
          <select name="message_template_id" id="message-template-select">
            <option value="">Selecciona plantilla</option>
            <?php foreach ($activeTemplates as $template):
              $templateId = isset($template['id_message_template']) ? (int)$template['id_message_template'] : 0;
              $templateTitle = isset($template['title']) ? (string)$template['title'] : '';
              $templateBody = isset($template['body']) ? (string)$template['body'] : '';
              $templateCode = isset($template['code']) ? (string)$template['code'] : '';
              $isSent = isset($sentTemplates[$templateId]);
            ?>
              <option value="<?php echo $templateId; ?>"
                data-title="<?php echo htmlspecialchars($templateTitle, ENT_QUOTES, 'UTF-8'); ?>"
                data-body="<?php echo htmlspecialchars($templateBody, ENT_QUOTES, 'UTF-8'); ?>"
                data-sent="<?php echo $isSent ? '1' : '0'; ?>">
                <?php echo htmlspecialchars($templateCode . ' - ' . $templateTitle, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
      </form>

      <div class="message-preview">
        <div class="message-preview-header">
          <h3>Vista previa</h3>
          <span class="message-preview-status" id="message-preview-status">Selecciona reservacion y plantilla.</span>
        </div>
        <div class="message-preview-body">
          <strong id="message-preview-title">--</strong>
          <p id="message-preview-text" class="muted">--</p>
        </div>
        <div class="message-preview-actions">
          <button type="button" id="message-send-button" class="button-secondary" data-wa-base="https://wa.me/">Enviar por WhatsApp</button>
        </div>
        <p class="muted message-preview-hints">Variables disponibles: {{guest_name}}, {{guest_phone}}, {{property_name}}, {{property_code}}, {{check_in}}, {{check_out}}, {{reservation_code}}, {{room_code}}, {{category_name}}.</p>
      </div>

      <?php if ($sentLogRows): ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Enviado</th>
                <th>Plantilla</th>
                <th>Titulo</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($sentLogRows as $row): ?>
                <tr>
                  <td><?php echo htmlspecialchars((string)$row['sent_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$row['template_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$row['template_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="reservation-tab-panel <?php echo $activeTab === 'messages-tab-templates' ? 'is-active' : ''; ?>" id="messages-tab-templates" data-tab-panel>
    <section class="card">
      <h2>Plantillas</h2>
      <?php if ($templates): ?>
        <div class="table-scroll">
          <table>
            <thead>
              <tr>
                <th>Codigo</th>
                <th>Titulo</th>
                <th>Propiedad</th>
                <th>Activa</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($templates as $template): ?>
                <tr>
                  <td><?php echo htmlspecialchars((string)$template['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$template['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)$template['property_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo isset($template['is_active']) && (int)$template['is_active'] === 1 ? 'Si' : 'No'; ?></td>
                  <td>
                    <form method="post">
                      <input type="hidden" name="messages_action" value="edit_template">
                      <input type="hidden" name="messages_active_tab" value="messages-tab-templates">
                      <input type="hidden" name="template_id" value="<?php echo (int)$template['id_message_template']; ?>">
                      <button type="submit" class="button-secondary">Editar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="muted">Sin plantillas registradas.</p>
      <?php endif; ?>

      <form method="post" class="form-grid grid-2 message-template-form">
        <input type="hidden" name="messages_action" value="save_template">
        <input type="hidden" name="messages_active_tab" value="messages-tab-templates">
        <input type="hidden" name="template_id" value="<?php echo $editTemplate ? (int)$editTemplate['id_message_template'] : 0; ?>">

        <label>
          Propiedad
          <select name="template_property_code">
            <option value="">Todas</option>
            <?php foreach ($properties as $property):
              $code = isset($property['code']) ? (string)$property['code'] : '';
              $name = isset($property['name']) ? (string)$property['name'] : '';
              $selected = $editTemplate && isset($editTemplate['property_code']) && $editTemplate['property_code'] === $code;
            ?>
              <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($code . ' - ' . $name, ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Codigo *
          <input type="text" name="template_code" required value="<?php echo $editTemplate ? htmlspecialchars((string)$editTemplate['code'], ENT_QUOTES, 'UTF-8') : ''; ?>">
        </label>
        <label class="full">
          Titulo *
          <input type="text" name="template_title" required value="<?php echo $editTemplate ? htmlspecialchars((string)$editTemplate['title'], ENT_QUOTES, 'UTF-8') : ''; ?>">
        </label>
        <label class="full">
          Cuerpo *
          <textarea name="template_body" rows="6" required><?php echo $editTemplate ? htmlspecialchars((string)$editTemplate['body'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
        </label>
        <label class="checkbox">
          <input type="checkbox" name="template_is_active" value="1" <?php echo !$editTemplate || (isset($editTemplate['is_active']) && (int)$editTemplate['is_active'] === 1) ? 'checked' : ''; ?>>
          Activa
        </label>
        <div class="form-actions full">
          <button type="submit"><?php echo $editTemplate ? 'Actualizar plantilla' : 'Guardar plantilla'; ?></button>
        </div>
      </form>
    </section>
  </div>
</div>

<script>
(function () {
  var container = document.querySelector('[data-reservation-tabs=\"messages\"]');
  if (container) {
    var triggers = container.querySelectorAll('.reservation-tab-trigger');
    var panels = container.querySelectorAll('[data-tab-panel]');
    function activate(targetId) {
      triggers.forEach(function (btn) {
        btn.classList.toggle('is-active', btn.getAttribute('data-tab-target') === targetId);
      });
      panels.forEach(function (panel) {
        panel.classList.toggle('is-active', panel.id === targetId);
      });
    }
    triggers.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.getAttribute('data-tab-target');
        if (targetId) activate(targetId);
      });
    });
  }

  var reservationSearch = document.getElementById('message-reservation-search');
  var reservationSelect = document.getElementById('message-reservation-select');
  var templateSelect = document.getElementById('message-template-select');
  var previewTitle = document.getElementById('message-preview-title');
  var previewText = document.getElementById('message-preview-text');
  var previewStatus = document.getElementById('message-preview-status');
  var sendButton = document.getElementById('message-send-button');
  var messageTitleInput = document.getElementById('message-title-input');
  var messageBodyInput = document.getElementById('message-body-input');
  var messagePhoneInput = document.getElementById('message-phone-input');
  var messageFullText = '';

  function sanitizePhone(value) {
    return (value || '').replace(/[^0-9]/g, '');
  }

  function buildPreview() {
    if (!reservationSelect || !templateSelect) return;
    var reservationOption = reservationSelect.options[reservationSelect.selectedIndex];
    var templateOption = templateSelect.options[templateSelect.selectedIndex];
    if (!reservationOption || !templateOption || !reservationOption.value || !templateOption.value) {
      previewTitle.textContent = '--';
      previewText.textContent = '--';
      previewStatus.textContent = 'Selecciona reservacion y plantilla.';
      sendButton.disabled = true;
      messageFullText = '';
      return;
    }
    var data = {
      guest_name: reservationOption.getAttribute('data-guest-name') || '',
      guest_phone: reservationOption.getAttribute('data-guest-phone') || '',
      property_name: reservationOption.getAttribute('data-property-name') || '',
      property_code: reservationOption.getAttribute('data-property-code') || '',
      check_in: reservationOption.getAttribute('data-check-in') || '',
      check_out: reservationOption.getAttribute('data-check-out') || '',
      reservation_code: reservationOption.getAttribute('data-reservation-code') || '',
      room_code: reservationOption.getAttribute('data-room-code') || '',
      category_name: reservationOption.getAttribute('data-category-name') || ''
    };
    var title = templateOption.getAttribute('data-title') || '';
    var body = templateOption.getAttribute('data-body') || '';
    Object.keys(data).forEach(function (key) {
      var token = new RegExp('{{' + key + '}}', 'g');
      title = title.replace(token, data[key]);
      body = body.replace(token, data[key]);
    });
    previewTitle.textContent = title || '--';
    previewText.textContent = body || '--';
    messageFullText = title;
    if (title && body) {
      messageFullText += '\n\n';
    }
    messageFullText += body;
    var sent = templateOption.getAttribute('data-sent') === '1';
    var phone = sanitizePhone(data.guest_phone);
    if (sent) {
      previewStatus.textContent = 'Este mensaje ya fue enviado.';
    } else if (!phone) {
      previewStatus.textContent = 'El huesped no tiene telefono registrado.';
    } else {
      previewStatus.textContent = 'Listo para enviar por WhatsApp.';
    }
    sendButton.disabled = sent || !phone;
    messageTitleInput.value = title;
    messageBodyInput.value = body;
    messagePhoneInput.value = phone;
  }

  if (reservationSearch && reservationSelect) {
    reservationSearch.addEventListener('input', function () {
      var term = reservationSearch.value.trim().toLowerCase();
      Array.prototype.forEach.call(reservationSelect.options, function (option) {
        if (!option.value) return;
        var search = option.getAttribute('data-search') || '';
        option.hidden = term !== '' && search.indexOf(term) === -1;
      });
    });
  }

  if (reservationSelect) {
    reservationSelect.addEventListener('change', buildPreview);
  }
  if (templateSelect) {
    templateSelect.addEventListener('change', buildPreview);
  }

  if (sendButton) {
    sendButton.addEventListener('click', function (event) {
      event.preventDefault();
      if (!reservationSelect || !templateSelect) return;
      var reservationOption = reservationSelect.options[reservationSelect.selectedIndex];
      var templateOption = templateSelect.options[templateSelect.selectedIndex];
      if (!reservationOption || !templateOption || !reservationOption.value || !templateOption.value) return;
      var phone = messagePhoneInput.value || '';
      var text = messageFullText || messageBodyInput.value || '';
      var base = sendButton.getAttribute('data-wa-base') || 'https://wa.me/';
      var waLink = base + phone + '?text=' + encodeURIComponent(text);
      window.open(waLink, '_blank');
      var form = document.getElementById('message-send-form');
      if (form) {
        form.submit();
      }
    });
  }

  buildPreview();
})();
</script>
