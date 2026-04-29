<?php
require_once 'session_check_admin.php';
require_once 'inc/permissions.php';
require_once 'inc/audit.php';
require_once 'config/db.php';

// ── Ensure primary key is auto-increment ─────────────────────────────────────
$conn->query("ALTER TABLE audit_logs MODIFY COLUMN id BIGINT NOT NULL AUTO_INCREMENT");

// ── Filters ───────────────────────────────────────────────────────────────────
$af_module = trim($_GET['af_module'] ?? '');
$af_action = trim($_GET['af_action'] ?? '');
$af_user   = trim($_GET['af_user']   ?? '');
$af_date   = trim($_GET['af_date']   ?? '');
$af_page   = max(1, (int)($_GET['af_page'] ?? 1));
$af_limit  = 20;
$af_offset = ($af_page - 1) * $af_limit;

$aw = "WHERE 1=1"; $ap = []; $at = '';
if ($af_module){ $aw .= " AND module=?";           $ap[] = $af_module;   $at .= 's'; }
if ($af_action){ $aw .= " AND action=?";           $ap[] = $af_action;   $at .= 's'; }
if ($af_user  ){ $aw .= " AND username LIKE ?";    $ap[] = "%$af_user%"; $at .= 's'; }
if ($af_date  ){ $aw .= " AND DATE(created_at)=?"; $ap[] = $af_date;     $at .= 's'; }

$cnt_s = $conn->prepare("SELECT COUNT(*) AS c FROM audit_logs $aw");
if ($ap) $cnt_s->bind_param($at, ...$ap);
$cnt_s->execute();
$af_total = (int)$cnt_s->get_result()->fetch_assoc()['c'];
$cnt_s->close();

$af_pages = max(1, (int)ceil($af_total / $af_limit));
$af_page  = min($af_page, $af_pages);
$af_offset = ($af_page - 1) * $af_limit;

$ap2 = $ap; $ap2[] = $af_limit; $ap2[] = $af_offset; $at2 = $at . 'ii';
$log_s = $conn->prepare("SELECT * FROM audit_logs $aw ORDER BY created_at DESC LIMIT ? OFFSET ?");
if ($ap2) $log_s->bind_param($at2, ...$ap2);
$log_s->execute();
$logs = $log_s->get_result()->fetch_all(MYSQLI_ASSOC);
$log_s->close();

$all_modules = $conn->query("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetch_all(MYSQLI_ASSOC);
$all_actions = $conn->query("SELECT DISTINCT action  FROM audit_logs ORDER BY action" )->fetch_all(MYSQLI_ASSOC);

// ── Quick stats ───────────────────────────────────────────────────────────────
$stats = [];
foreach (['CREATE','UPDATE','DELETE','LOGIN','LOGOUT'] as $act) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM audit_logs WHERE action='$act'")->fetch_assoc();
    $stats[$act] = (int)($r['c'] ?? 0);
}
$stats['TOTAL'] = $af_total ?: (int)($conn->query("SELECT COUNT(*) AS c FROM audit_logs")->fetch_assoc()['c'] ?? 0);

function actionBadge(string $a): string {
    return match(strtoupper($a)) {
        'CREATE' => 'background:#d1fae5;color:#065f46',
        'UPDATE' => 'background:#dbeafe;color:#1e40af',
        'DELETE' => 'background:#fee2e2;color:#991b1b',
        'CANCEL' => 'background:#fef3c7;color:#92400e',
        'LOGIN'  => 'background:#ede9fe;color:#6b21a8',
        'LOGOUT' => 'background:#f1f5f9;color:#475569',
        default  => 'background:#f1f5f9;color:#374151',
    };
}
function moduleBadge(string $m): string {
    return match(strtoupper($m)) {
        'PR'         => 'background:#dbeafe;color:#1d4ed8',
        'RFQ'        => 'background:#d1fae5;color:#065f46',
        'PO'         => 'background:#fef3c7;color:#b45309',
        'IAR'        => 'background:#ede9fe;color:#6b21a8',
        'DV'         => 'background:#fee2e2;color:#991b1b',
        'PAYROLL'    => 'background:#f0fdf4;color:#15803d',
        'PAYROLL_DV' => 'background:#fef9c3;color:#854d0e',
        'SUB_PR'     => 'background:#e0f2fe;color:#0369a1',
        'AUTH'       => 'background:#f5f3ff;color:#5b21b6',
        'USER'       => 'background:#eff6ff;color:#2563eb',
        default      => 'background:#f8fafc;color:#475569',
    };
}

include 'inc/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
.sb-content-wrap { margin-left: 0 !important; }
:root{--navy:#0f172a;--navy2:#1e3a5f;--blue2:#2563eb;--border:#e2e8f0;--surface:#f8fafc;--text:#0f172a;--muted:#64748b;--card:#ffffff}
body{font-family:'DM Sans',sans-serif;background:var(--surface)}

/* Hero */
.at-hero{background:linear-gradient(135deg,var(--navy) 0%,#3730a3 60%,#4f46e5 100%);padding:1.5rem 2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;position:relative;overflow:hidden;}
.at-hero::before{content:'';position:absolute;top:-60px;right:-60px;width:300px;height:300px;background:radial-gradient(circle,rgba(255,255,255,.07) 0%,transparent 70%);border-radius:50%;}
.at-hero::after{content:'';position:absolute;bottom:-40px;left:30%;width:200px;height:200px;background:radial-gradient(circle,rgba(99,102,241,.2) 0%,transparent 70%);border-radius:50%;}
.at-hero-left{display:flex;align-items:center;gap:1.25rem;position:relative;z-index:1;}
.at-hero-icon{background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);border-radius:.875rem;padding:.75rem;box-shadow:0 0 20px rgba(255,255,255,.1);}
.at-hero h1{font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:700;color:#fff;margin:0;}
.at-hero p{font-size:.8rem;color:rgba(255,255,255,.65);margin:.2rem 0 0;}
.at-hero-right{display:flex;gap:.75rem;align-items:center;position:relative;z-index:1;}

/* Stat chips */
.stat-chips{display:flex;gap:.5rem;flex-wrap:wrap;}
.stat-chip{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);backdrop-filter:blur(8px);border-radius:.625rem;padding:.5rem .875rem;text-align:center;min-width:75px;}
.stat-chip-val{font-size:1.125rem;font-weight:700;color:#fff;line-height:1;}
.stat-chip-lbl{font-size:.6rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.06em;margin-top:.15rem;}

/* Buttons */
.btn-back{display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#fff;font-weight:600;font-size:.875rem;padding:.55rem 1.125rem;border-radius:.625rem;cursor:pointer;transition:background .2s;text-decoration:none;font-family:inherit;}
.btn-back:hover{background:rgba(255,255,255,.25);}
.btn-filter{display:inline-flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,#4f46e5,#4338ca);color:#fff;font-weight:600;font-size:.8rem;padding:.5rem 1rem;border-radius:.5rem;border:none;cursor:pointer;box-shadow:0 3px 10px rgba(79,70,229,.35);transition:all .2s;font-family:inherit;}
.btn-filter:hover{transform:translateY(-1px);box-shadow:0 5px 14px rgba(79,70,229,.45);}
.btn-clear{display:inline-flex;align-items:center;gap:.4rem;background:#f1f5f9;color:var(--muted);font-weight:600;font-size:.8rem;padding:.5rem .875rem;border-radius:.5rem;border:1px solid var(--border);cursor:pointer;text-decoration:none;font-family:inherit;}
.btn-clear:hover{background:#e2e8f0;}

/* Content area */
.at-content{padding:1.5rem 2rem;max-width:1440px;margin:0 auto;width:100%;box-sizing:border-box;}

/* Filter card */
.filter-card{background:var(--card);border-radius:.875rem;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,.04);margin-bottom:1.25rem;overflow:hidden;}
.filter-card-head{padding:.75rem 1.25rem;background:#f8fafc;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.5rem;font-size:.8rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;}
.filter-body{padding:.875rem 1.25rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:flex-end;}
.filter-group{display:flex;flex-direction:column;gap:.3rem;}
.filter-label{font-size:.7rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;}
.filter-input{border:1.5px solid var(--border);border-radius:.5rem;padding:.45rem .75rem;font-size:.8rem;outline:none;transition:.2s;font-family:inherit;background:#fff;}
.filter-input:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.1);}
.filter-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%2364748b' d='M7 10l5 5 5-5H7z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .6rem center;padding-right:1.75rem;cursor:pointer;}
.results-info{margin-left:auto;font-size:.78rem;color:var(--muted);align-self:flex-end;}

/* Table card */
.table-card{background:var(--card);border-radius:.875rem;border:1px solid var(--border);box-shadow:0 1px 3px rgba(0,0,0,.04);overflow:hidden;}
.table-wrap{overflow-x:auto;overflow-y:auto;max-height:calc(100vh - 360px);min-height:300px;}
table.at-table{width:100%;border-collapse:collapse;font-size:.8rem;}
table.at-table thead th{background:#f8fafc;padding:.7rem 1rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);font-weight:700;border-bottom:2px solid var(--border);position:sticky;top:0;z-index:5;white-space:nowrap;}
table.at-table tbody tr{border-bottom:1px solid var(--border);transition:background .1s;}
table.at-table tbody tr:hover{background:#fafbff;}
table.at-table tbody td{padding:.7rem 1rem;vertical-align:middle;}
.badge{display:inline-block;padding:.18rem .55rem;border-radius:4px;font-size:.68rem;font-weight:700;letter-spacing:.02em;}
.detail-btn{background:#f1f5f9;border:1px solid var(--border);padding:.22rem .55rem;border-radius:4px;cursor:pointer;font-size:.68rem;font-weight:600;color:var(--muted);transition:.15s;}
.detail-btn:hover{background:#e2e8f0;color:var(--text);}
.detail-row{background:#fffbeb;}
.detail-row td{padding:.875rem 1.5rem;}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.detail-pane-label{font-size:.68rem;font-weight:700;text-transform:uppercase;margin-bottom:.4rem;}
.detail-pre{padding:.75rem;border-radius:.375rem;font-size:.72rem;overflow-x:auto;white-space:pre-wrap;word-break:break-all;max-height:180px;overflow-y:auto;margin:0;}
.empty-row td{text-align:center;padding:3rem;color:var(--muted);font-style:italic;}

/* Pagination */
.pag-bar{padding:.875rem 1.25rem;background:#f8fafc;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;}
.pag-info{font-size:.8rem;color:var(--muted);}
.pag-links{display:flex;gap:.3rem;align-items:center;}
.pag-btn{padding:.35rem .65rem;font-size:.78rem;border-radius:.375rem;border:1px solid var(--border);background:#fff;color:var(--muted);text-decoration:none;transition:.15s;display:inline-block;}
.pag-btn:hover{background:#f1f5f9;color:var(--text);}
.pag-btn.active{background:#4f46e5;color:#fff;border-color:#4f46e5;font-weight:700;}

@media(max-width:768px){.at-hero{padding:1rem;}.at-content{padding:1rem;}.stat-chips{display:none;}.detail-grid{grid-template-columns:1fr;}}
</style>

<!-- HERO -->
<div class="at-hero">
  <div class="at-hero-left">
    <div class="at-hero-icon">
      <img src="dti.jpg" alt="DTI" style="width:52px;height:52px;border-radius:.5rem;display:block;object-fit:cover;">
    </div>
    <div>
      <h1><i class="fas fa-scroll" style="color:#a5b4fc;margin-right:.5rem;font-size:1.25rem;"></i>System Audit Trail</h1>
      <p>Full security log — every action performed in the system, by every user</p>
    </div>
  </div>
  <div class="at-hero-right">
    <!-- Quick stats -->
    <div class="stat-chips">
      <div class="stat-chip"><div class="stat-chip-val"><?= number_format($stats['TOTAL']) ?></div><div class="stat-chip-lbl">Total</div></div>
      <div class="stat-chip" style="border-color:rgba(16,185,129,.4)"><div class="stat-chip-val" style="color:#6ee7b7"><?= number_format($stats['CREATE']) ?></div><div class="stat-chip-lbl">Creates</div></div>
      <div class="stat-chip" style="border-color:rgba(96,165,250,.4)"><div class="stat-chip-val" style="color:#93c5fd"><?= number_format($stats['UPDATE']) ?></div><div class="stat-chip-lbl">Updates</div></div>
      <div class="stat-chip" style="border-color:rgba(248,113,113,.4)"><div class="stat-chip-val" style="color:#fca5a5"><?= number_format($stats['DELETE']) ?></div><div class="stat-chip-lbl">Deletes</div></div>
      <div class="stat-chip" style="border-color:rgba(196,181,253,.4)"><div class="stat-chip-val" style="color:#c4b5fd"><?= number_format($stats['LOGIN']) ?></div><div class="stat-chip-lbl">Logins</div></div>
    </div>
    <a href="admin.php" class="btn-back"><i class="fas fa-arrow-left fa-sm"></i> Back to Admin</a>
  </div>
</div>

<div class="at-content">

  <!-- Filter card -->
  <div class="filter-card">
    <div class="filter-card-head"><i class="fas fa-filter fa-xs"></i> Filter Audit Records</div>
    <form method="GET" class="filter-body">
      <input type="hidden" name="af_page" value="1">

      <div class="filter-group">
        <span class="filter-label">Module</span>
        <select name="af_module" class="filter-input filter-select" style="min-width:130px;">
          <option value="">All Modules</option>
          <?php foreach($all_modules as $m): ?>
            <option value="<?= htmlspecialchars($m['module']) ?>" <?= $af_module===$m['module']?'selected':'' ?>>
              <?= htmlspecialchars($m['module']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <span class="filter-label">Action</span>
        <select name="af_action" class="filter-input filter-select" style="min-width:120px;">
          <option value="">All Actions</option>
          <?php foreach($all_actions as $a): ?>
            <option value="<?= htmlspecialchars($a['action']) ?>" <?= $af_action===$a['action']?'selected':'' ?>>
              <?= htmlspecialchars($a['action']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <span class="filter-label">Username</span>
        <input type="text" name="af_user" value="<?= htmlspecialchars($af_user) ?>"
               placeholder="Search user…" class="filter-input" style="width:140px;">
      </div>

      <div class="filter-group">
        <span class="filter-label">Date</span>
        <input type="date" name="af_date" value="<?= htmlspecialchars($af_date) ?>"
               class="filter-input">
      </div>

      <button type="submit" class="btn-filter">
        <i class="fas fa-search fa-xs"></i> Search
      </button>

      <?php if($af_module || $af_action || $af_user || $af_date): ?>
        <a href="audit_trail.php" class="btn-clear">
          <i class="fas fa-times fa-xs"></i> Clear
        </a>
      <?php endif; ?>

      <div class="results-info">
        Found <strong><?= number_format($af_total) ?></strong> record<?= $af_total !== 1 ? 's' : '' ?>
        <?php if($af_module||$af_action||$af_user||$af_date): ?>
          <span style="color:#4f46e5;font-weight:600;"> (filtered)</span>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Table -->
  <div class="table-card">
    <div class="table-wrap">
      <table class="at-table">
        <thead>
          <tr>
            <th style="width:160px;">Timestamp</th>
            <th>User</th>
            <th style="width:100px;">Module</th>
            <th style="width:90px;">Action</th>
            <th>Reference No.</th>
            <th>IP Address</th>
            <th style="width:70px;text-align:center;">Detail</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($logs): foreach ($logs as $log):
            $ts        = date('M d, Y', strtotime($log['created_at'])) . '<br><span style="font-size:.68rem;color:var(--muted);">' . date('H:i:s', strtotime($log['created_at'])) . '</span>';
            $hasBefore = !empty($log['before_data']) && $log['before_data'] !== 'null';
            $hasAfter  = !empty($log['after_data'])  && $log['after_data']  !== 'null';
            $rowId     = 'dr_' . $log['id'];
          ?>
          <tr>
            <td style="white-space:nowrap;font-size:.78rem;color:var(--muted);">
              <i class="fas fa-clock fa-xs" style="opacity:.4;margin-right:.3rem;"></i><?= $ts ?>
            </td>
            <td>
              <div style="font-weight:600;color:var(--text);font-size:.8rem;"><?= htmlspecialchars($log['username']) ?></div>
              <div style="font-size:.68rem;color:var(--muted);"><?= htmlspecialchars($log['role']) ?></div>
            </td>
            <td>
              <span class="badge" style="<?= moduleBadge($log['module']) ?>"><?= htmlspecialchars($log['module']) ?></span>
            </td>
            <td>
              <span class="badge" style="<?= actionBadge($log['action']) ?>"><?= htmlspecialchars($log['action']) ?></span>
            </td>
            <td style="font-weight:600;color:#2563eb;font-family:monospace;font-size:.78rem;">
              <?= htmlspecialchars($log['reference_no'] ?? '—') ?>
            </td>
            <td style="color:var(--muted);font-family:monospace;font-size:.75rem;">
              <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
            </td>
            <td style="text-align:center;">
              <?php if ($hasBefore || $hasAfter): ?>
                <button class="detail-btn" onclick="toggleDetail('<?= $rowId ?>')">
                  <i class="fas fa-eye fa-xs"></i>
                </button>
              <?php else: ?>
                <span style="color:#cbd5e1;font-size:.7rem;">—</span>
              <?php endif; ?>
            </td>
          </tr>

          <?php if ($hasBefore || $hasAfter): ?>
          <tr class="detail-row" id="<?= $rowId ?>" style="display:none;">
            <td colspan="7">
              <div class="detail-grid" style="grid-template-columns:<?= ($hasBefore&&$hasAfter)?'1fr 1fr':'1fr' ?>;">
                <?php if ($hasBefore): ?>
                <div>
                  <div class="detail-pane-label" style="color:#92400e;">
                    <i class="fas fa-history fa-xs"></i> Before
                  </div>
                  <pre class="detail-pre" style="background:#fef3c7;"><?= htmlspecialchars(json_encode(json_decode($log['before_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </div>
                <?php endif; ?>
                <?php if ($hasAfter): ?>
                <div>
                  <div class="detail-pane-label" style="color:#065f46;">
                    <i class="fas fa-check-circle fa-xs"></i> After
                  </div>
                  <pre class="detail-pre" style="background:#d1fae5;"><?= htmlspecialchars(json_encode(json_decode($log['after_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                </div>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endif; ?>

          <?php endforeach; else: ?>
          <tr class="empty-row"><td colspan="7">No audit records found matching your criteria.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($af_pages > 1):
      $pq = http_build_query(array_filter(['af_module'=>$af_module,'af_action'=>$af_action,'af_user'=>$af_user,'af_date'=>$af_date]));
      $from = $af_offset + 1;
      $to   = min($af_offset + $af_limit, $af_total);
    ?>
    <div class="pag-bar">
      <div class="pag-info">
        Showing <strong><?= $from ?>–<?= $to ?></strong> of <strong><?= number_format($af_total) ?></strong> records
      </div>
      <div class="pag-links">
        <?php if($af_page > 1): ?>
          <a href="?<?= $pq ?>&af_page=1" class="pag-btn" title="First">«</a>
          <a href="?<?= $pq ?>&af_page=<?= $af_page-1 ?>" class="pag-btn">‹ Prev</a>
        <?php endif; ?>

        <?php
        $p_start = max(1, $af_page - 2);
        $p_end   = min($af_pages, $af_page + 2);
        for ($pi = $p_start; $pi <= $p_end; $pi++):
        ?>
          <a href="?<?= $pq ?>&af_page=<?= $pi ?>"
             class="pag-btn <?= $pi === $af_page ? 'active' : '' ?>">
            <?= $pi ?>
          </a>
        <?php endfor; ?>

        <?php if($af_page < $af_pages): ?>
          <a href="?<?= $pq ?>&af_page=<?= $af_page+1 ?>" class="pag-btn">Next ›</a>
          <a href="?<?= $pq ?>&af_page=<?= $af_pages ?>" class="pag-btn" title="Last">»</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /table-card -->
</div><!-- /at-content -->

<script>
function toggleDetail(id) {
  const row = document.getElementById(id);
  if (!row) return;
  const isHidden = row.style.display === 'none';
  row.style.display = isHidden ? 'table-row' : 'none';
  // Update button icon
  const btn = row.previousElementSibling?.querySelector('.detail-btn');
  if (btn) btn.innerHTML = isHidden
    ? '<i class="fas fa-eye-slash fa-xs"></i>'
    : '<i class="fas fa-eye fa-xs"></i>';
}
</script>

<?php include 'inc/footer.php'; ?>
