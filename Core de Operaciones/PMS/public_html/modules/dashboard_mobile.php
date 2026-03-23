<?php
ob_start();
require __DIR__ . '/dashboard.php';
$dashboardMobileHtml = ob_get_clean();

// Keep dashboard links/forms inside this mobile module.
$dashboardMobileHtml = strtr($dashboardMobileHtml, array(
    'index.php?view=dashboard&' => 'index.php?view=dashboard_mobile&',
    'index.php?view=dashboard"' => 'index.php?view=dashboard_mobile"',
    "index.php?view=dashboard'" => "index.php?view=dashboard_mobile'",
    '?view=dashboard&' => '?view=dashboard_mobile&',
    '?view=dashboard"' => '?view=dashboard_mobile"',
    "?view=dashboard'" => "?view=dashboard_mobile'"
));
$dashboardMobileHtml = preg_replace(
    '/(<input[^>]+name=["\']view["\'][^>]+value=["\'])dashboard(["\'])/i',
    '$1dashboard_mobile$2',
    $dashboardMobileHtml
);
$dashboardMobileHtml = preg_replace('/<section class="card">/i', '<section class="card dashboard-mobile-view">', $dashboardMobileHtml, 1);
?>

<section class="dashboard-mobile-root" data-dashboard-mobile-root>
  <style>
    .dashboard-mobile-root {
      width: 100%;
      min-width: 0;
    }
    .dashboard-mobile-view .table-scroll {
      max-width: 100%;
    }

    @media (max-width: 900px) {
      body.is-dashboard-mobile-view .app-header {
        padding: 8px 10px;
      }

      body.is-dashboard-mobile-view .header-bar {
        gap: 8px;
      }

      body.is-dashboard-mobile-view .brand-title,
      body.is-dashboard-mobile-view .user-meta {
        display: none;
      }

      body.is-dashboard-mobile-view .icon-button {
        width: 32px;
        height: 32px;
        border-radius: 10px;
      }

      body.is-dashboard-mobile-view .app-main {
        margin: 8px 0 12px;
        padding: 0 6px;
        gap: 8px;
      }

      body.is-dashboard-mobile-view .app-footer {
        display: none;
      }

      .dashboard-mobile-view {
        margin: 0;
        padding: 10px;
        border-radius: 12px;
      }

      .dashboard-mobile-view .dashboard-day-header {
        display: grid;
        gap: 8px;
        margin-bottom: 8px;
      }

      .dashboard-mobile-view .dashboard-property-summary h2 {
        font-size: clamp(0.96rem, 4.4vw, 1.15rem);
      }

      .dashboard-mobile-view .dashboard-property-summary .muted {
        font-size: 0.74rem;
      }

      .dashboard-mobile-view .dashboard-date-form {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
        width: 100%;
      }

      .dashboard-mobile-view .dashboard-date-form label,
      .dashboard-mobile-view .dashboard-date-form .dashboard-property-select {
        min-width: 0;
        width: 100%;
      }

      .dashboard-mobile-view .dashboard-date-form input,
      .dashboard-mobile-view .dashboard-date-form select,
      .dashboard-mobile-view .dashboard-date-form button {
        width: 100%;
        min-height: 40px;
        padding: 7px 9px;
        font-size: clamp(0.8rem, 3.2vw, 0.92rem);
      }

      .dashboard-mobile-view .dashboard-date-form button {
        margin-top: 2px;
      }

      .dashboard-mobile-view .analytics-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 7px;
      }

      .dashboard-mobile-view .analytics-card {
        min-width: 0;
        padding: 9px;
        border-radius: 11px;
        gap: 4px;
      }

      .dashboard-mobile-view .analytics-card small {
        font-size: 0.68rem;
      }

      .dashboard-mobile-view .analytics-card strong {
        font-size: clamp(0.95rem, 4vw, 1.1rem);
        line-height: 1.1;
      }

      .dashboard-mobile-view .analytics-card span {
        font-size: 0.67rem;
        line-height: 1.2;
      }

      .dashboard-mobile-view .dashboard-arrivals-chart {
        margin-top: 12px;
        margin-bottom: 16px;
        gap: 10px;
      }

      .dashboard-mobile-view .dashboard-arrivals-header {
        display: grid;
        gap: 8px;
      }

      .dashboard-mobile-view .dashboard-arrivals-filter {
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
      }

      .dashboard-mobile-view .dashboard-arrivals-filter label {
        min-width: 0;
      }

      .dashboard-mobile-view .arrivals-chart {
        overflow-x: auto;
        padding: 10px 8px;
      }

      .dashboard-mobile-view .arrivals-chart-bars {
        grid-template-columns: repeat(auto-fit, minmax(30px, 1fr));
        gap: 6px;
        min-height: 130px;
      }

      .dashboard-mobile-view .arrivals-bar-label {
        font-size: 0.62rem;
      }

      .dashboard-mobile-view .dashboard-activities-block {
        margin-top: 12px;
        padding-top: 0;
      }

      .dashboard-mobile-view .daily-property,
      .dashboard-mobile-view .availability-property {
        border-radius: 12px;
        padding: 9px;
        gap: 8px;
      }

      .dashboard-mobile-view .reservation-tab-nav {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        gap: 6px;
        padding-bottom: 4px;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
      }

      .dashboard-mobile-view .reservation-tab-trigger {
        flex: 0 0 auto;
        white-space: nowrap;
        min-height: 34px;
        font-size: 0.74rem;
        padding: 6px 9px;
      }

      .dashboard-mobile-view .daily-list {
        gap: 8px;
      }

      .dashboard-mobile-view .daily-row {
        padding: 8px;
        border-radius: 10px;
        gap: 8px;
      }

      .dashboard-mobile-view .daily-row-main {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px;
      }

      .dashboard-mobile-view .daily-field {
        min-width: 0;
      }

      .dashboard-mobile-view .daily-field .label {
        font-size: 0.63rem;
      }

      .dashboard-mobile-view .daily-field span:not(.label) {
        font-size: 0.75rem;
        overflow-wrap: anywhere;
      }

      .dashboard-mobile-view .daily-row-actions,
      .dashboard-mobile-view .daily-action-stack {
        width: 100%;
        min-width: 0;
      }

      .dashboard-mobile-view .daily-action-form button,
      .dashboard-mobile-view .daily-row-actions button {
        width: 100%;
        min-height: 34px;
        font-size: 0.8rem;
      }

      .dashboard-mobile-view .dashboard-obligation-type-nav {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
      }

      .dashboard-mobile-view .dashboard-obligation-type-option {
        flex: 0 0 auto;
      }

      .dashboard-mobile-view .dashboard-obligation-type-label {
        font-size: 0.74rem;
        padding: 7px 10px;
      }

      .dashboard-mobile-view .form-inline {
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 6px !important;
      }

      .dashboard-mobile-view .form-inline > * {
        min-width: 0 !important;
        width: 100%;
      }

      .dashboard-mobile-view .form-inline .muted,
      .dashboard-mobile-view .form-inline button[value="obligation_apply_all"] {
        grid-column: 1 / -1;
      }

      .dashboard-mobile-view .table-scroll {
        overflow: visible;
      }

      .dashboard-mobile-view table.is-mobile-cards {
        width: 100%;
        border-collapse: separate;
      }

      .dashboard-mobile-view table.is-mobile-cards thead {
        display: none;
      }

      .dashboard-mobile-view table.is-mobile-cards tbody,
      .dashboard-mobile-view table.is-mobile-cards tr,
      .dashboard-mobile-view table.is-mobile-cards td {
        display: block;
        width: 100%;
      }

      .dashboard-mobile-view table.is-mobile-cards tr {
        margin: 0 0 8px;
        border: 1px solid rgba(148, 163, 184, 0.2);
        border-radius: 11px;
        padding: 8px;
        background: rgba(15, 23, 42, 0.45);
      }

      .dashboard-mobile-view table.is-mobile-cards td {
        border: 0;
        padding: 4px 0;
        font-size: 0.76rem;
        overflow-wrap: anywhere;
      }

      .dashboard-mobile-view table.is-mobile-cards td::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 2px;
        color: #94a3b8;
        font-size: 0.64rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
      }

      .dashboard-mobile-view table.is-mobile-cards td:last-child {
        padding-top: 8px;
      }

      .dashboard-mobile-view table.is-mobile-cards td:last-child::before {
        margin-bottom: 4px;
      }

      .dashboard-mobile-view table.is-mobile-cards td:last-child form {
        display: grid !important;
        grid-template-columns: 1fr 1fr;
        gap: 6px !important;
      }

      .dashboard-mobile-view table.is-mobile-cards td:last-child form > * {
        min-width: 0 !important;
        width: 100%;
      }

      .dashboard-mobile-view .dashboard-availability-inline .availability-header,
      .dashboard-mobile-view .availability-property .availability-header {
        display: grid;
        gap: 8px;
      }

      .dashboard-mobile-view .dashboard-arrivals-list {
        margin-top: 12px;
      }
    }

    @media (max-width: 520px) {
      .dashboard-mobile-view .analytics-grid {
        grid-template-columns: 1fr;
      }

      .dashboard-mobile-view .daily-row-main {
        grid-template-columns: 1fr;
      }

      .dashboard-mobile-view .form-inline,
      .dashboard-mobile-view table.is-mobile-cards td:last-child form {
        grid-template-columns: 1fr !important;
      }
    }
  </style>

  <?php echo $dashboardMobileHtml; ?>

  <script>
    window.addEventListener('DOMContentLoaded', function () {
      document.body.classList.add('is-dashboard-mobile-view');
      var viewportMedia = window.matchMedia('(max-width: 900px)');

      function applyMobileTableCards(isMobile) {
        var tables = document.querySelectorAll('.dashboard-mobile-view table');
        tables.forEach(function (table) {
          if (!isMobile) {
            table.classList.remove('is-mobile-cards');
            return;
          }
          if (table.classList.contains('is-mobile-cards')) {
            return;
          }
          var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (headerCell) {
            return (headerCell.textContent || '').trim();
          });
          if (!headers.length) {
            return;
          }
          table.querySelectorAll('tbody tr').forEach(function (row) {
            var dataCells = row.querySelectorAll('td');
            dataCells.forEach(function (cell, idx) {
              if (!cell.hasAttribute('data-label')) {
                cell.setAttribute('data-label', headers[idx] || ('Campo ' + (idx + 1)));
              }
            });
          });
          table.classList.add('is-mobile-cards');
        });
      }

      function syncMobileDashboardState() {
        applyMobileTableCards(viewportMedia.matches);
      }

      if (viewportMedia.addEventListener) {
        viewportMedia.addEventListener('change', syncMobileDashboardState);
      } else if (viewportMedia.addListener) {
        viewportMedia.addListener(syncMobileDashboardState);
      }

      syncMobileDashboardState();

      var availabilityBlocks = document.querySelectorAll('.dashboard-mobile-view [data-availability-collapsible]');
      availabilityBlocks.forEach(function (block) {
        block.classList.add('is-collapsed');
      });
    });
  </script>
</section>
