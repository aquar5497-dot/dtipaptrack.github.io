<?php
require_once 'session_check_admin.php';
require_once 'inc/permissions.php';
require_once 'config/db.php';

// ── Ensure permissions column exists (auto-migration) ────────────────────────
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions JSON DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_suspended TINYINT(1) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended_reason VARCHAR(255) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_cleared_at DATETIME DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS notif_seen_at    DATETIME DEFAULT NULL");

// ── Handle POST actions ───────────────────────────────────────────────────────
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role'] ?? '');
    $user_id  = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;

    // Collect permissions from checkboxes
    $all_perm_keys = [];
    foreach (getPermissionGroups() as $gdata) {
        $all_perm_keys = array_merge($all_perm_keys, array_keys($gdata['perms']));
    }
    $selected_perms = [];
    foreach ($all_perm_keys as $perm) {
        if (!empty($_POST['perm_' . $perm])) $selected_perms[] = $perm;
    }
    $permissions_json = json_encode($selected_perms);

    if ($action === 'create') {
        if ($name && $username && $password && $role) {
            $chk = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $chk->bind_param("s", $username);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $message = "Username already exists.";
                $message_type = 'error';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO users (name, username, password, role, permissions) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("sssss", $name, $username, $hashed, $role, $permissions_json);
                $ins->execute(); logAudit('USER','CREATE',$conn->insert_id,$username,[],[]); $ins->close();
                $message = "User <strong>" . htmlspecialchars($username) . "</strong> created successfully!";
                $message_type = 'success';
            }
            $chk->close();
        } else {
            $message = "Please fill in all required fields.";
            $message_type = 'error';
        }
    } elseif ($action === 'edit' && $user_id) {
        if ($name && $username && $role) {
            if (!empty($password)) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $upd = $conn->prepare("UPDATE users SET name=?, username=?, password=?, role=?, permissions=? WHERE id=?");
                $upd->bind_param("sssssi", $name, $username, $hashed, $role, $permissions_json, $user_id);
            } else {
                $upd = $conn->prepare("UPDATE users SET name=?, username=?, role=?, permissions=? WHERE id=?");
                $upd->bind_param("ssssi", $name, $username, $role, $permissions_json, $user_id);
            }
            $upd->execute(); logAudit('USER','UPDATE',$user_id,$username,[],[]); $upd->close();
            if ($user_id === (int)$_SESSION['user_id']) $_SESSION['permissions'] = $selected_perms;
            $message = "User <strong>" . htmlspecialchars($username) . "</strong> updated successfully!";
            $message_type = 'success';
        } else {
            $message = "Please fill in all required fields.";
            $message_type = 'error';
        }
    }
}

if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if ($del_id !== (int)$_SESSION['user_id']) {
        $del = $conn->prepare("DELETE FROM users WHERE id=?");
        $del->bind_param("i", $del_id); $del->execute(); logAudit('USER','DELETE',$del_id,'',['id'=>$del_id],[]); $del->close();
        $message = "User deleted successfully.";
        $message_type = 'success';
    } else {
        $message = "You cannot delete your own account.";
        $message_type = 'error';
    }
}

// ── Handle Suspend / Unsuspend ────────────────────────────────────────────────
if (isset($_GET['suspend'])) {
    $sus_id = intval($_GET['suspend']);
    if ($sus_id !== (int)$_SESSION['user_id']) {
        $reason = trim($_GET['reason'] ?? 'Suspended by Administrator');
        $sus = $conn->prepare("UPDATE users SET is_suspended=1, suspended_reason=? WHERE id=?");
        $sus->bind_param("si", $reason, $sus_id);
        $sus->execute(); $sus->close();
        $sus_uname = $conn->query("SELECT username FROM users WHERE id=$sus_id")->fetch_assoc()['username'] ?? '';
        logAudit('USER','SUSPEND',$sus_id,$sus_uname,['is_suspended'=>0],['is_suspended'=>1,'reason'=>$reason]);
        $message = "User suspended successfully.";
        $message_type = 'success';
    } else {
        $message = "You cannot suspend your own account.";
        $message_type = 'error';
    }
}

if (isset($_GET['unsuspend'])) {
    $uns_id = intval($_GET['unsuspend']);
    $uns = $conn->prepare("UPDATE users SET is_suspended=0, suspended_reason=NULL WHERE id=?");
    $uns->bind_param("i", $uns_id);
    $uns->execute(); $uns->close();
    $uns_uname = $conn->query("SELECT username FROM users WHERE id=$uns_id")->fetch_assoc()['username'] ?? '';
    logAudit('USER','UNSUSPEND',$uns_id,$uns_uname,['is_suspended'=>1],['is_suspended'=>0]);
    $message = "User account restored successfully.";
    $message_type = 'success';
}

// ── Fetch users ───────────────────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$like   = "%$search%";
$stmt   = $conn->prepare("SELECT * FROM users WHERE name LIKE ? OR username LIKE ? OR role LIKE ? ORDER BY id ASC");
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_users = count($users);
$admin_count = count(array_filter($users, fn($u) => $u['role'] === 'Administrator'));
$perm_groups = getPermissionGroups();
include 'inc/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root{--navy:#0f172a;--navy2:#1e3a5f;--blue:#1d4ed8;--blue2:#2563eb;--accent:#0ea5e9;--gold:#f59e0b;--green:#10b981;--red:#ef4444;--surface:#f8fafc;--card:#ffffff;--border:#e2e8f0;--text:#0f172a;--muted:#64748b}
/* Admin page has no sidebar — override content wrap margin */
.sb-content-wrap { margin-left: 0 !important; }
body{font-family:'DM Sans',sans-serif;background:var(--surface)}
/* Dark mode for admin page body */
html.dark body { background:var(--surface) !important; }
html.dark .admin-hero::before { opacity:.5; }
.admin-hero{background:linear-gradient(135deg,var(--navy) 0%,var(--navy2) 60%,#1e40af 100%);padding:1.5rem 2rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;position:relative;overflow:visible}
.admin-hero::before{content:'';position:absolute;top:-60px;right:-60px;width:280px;height:280px;background:radial-gradient(circle,rgba(255,255,255,.07) 0%,transparent 70%);border-radius:50%}
.hero-left h1{font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:700;color:#fff}
.hero-left p{font-size:.8rem;color:rgba(255,255,255,.65);margin-top:.2rem}
.hero-right{display:flex;gap:1rem;flex-wrap:wrap;align-items:center;position:relative;z-index:2}
.hero-stat{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.15);backdrop-filter:blur(8px);border-radius:.75rem;padding:.65rem 1.25rem;text-align:center;min-width:90px}
.hero-stat-val{font-size:1.5rem;font-weight:700;color:#fff;line-height:1}
.hero-stat-lbl{font-size:.65rem;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.06em;margin-top:.2rem}
.admin-content{flex:1;padding:1.75rem 2rem;max-width:1400px;margin:0 auto;width:100%}
.toolbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;margin-bottom:1.25rem}
.search-box{display:flex;align-items:center;gap:.5rem;background:#fff;border:1px solid var(--border);border-radius:.625rem;padding:.5rem .75rem;flex:1;max-width:360px}
.search-box input{border:none;outline:none;font-size:.875rem;width:100%;font-family:inherit}
.btn-primary{display:inline-flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,var(--blue2),#1d4ed8);color:#fff;font-weight:600;font-size:.875rem;padding:.6rem 1.25rem;border-radius:.625rem;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(37,99,235,.35);transition:all .2s;text-decoration:none;font-family:inherit}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(37,99,235,.45)}
.btn-green{background:linear-gradient(135deg,#059669,#10b981);box-shadow:0 4px 12px rgba(16,185,129,.35)}
.btn-cancel{display:inline-flex;align-items:center;gap:.5rem;background:#f1f5f9;color:var(--muted);font-weight:600;font-size:.875rem;padding:.6rem 1.25rem;border-radius:.625rem;border:1px solid var(--border);cursor:pointer;transition:.15s;font-family:inherit}
.btn-cancel:hover{background:#e2e8f0}
.alert{padding:.875rem 1.25rem;border-radius:.625rem;font-size:.875rem;margin-bottom:1rem;display:flex;align-items:center;gap:.75rem}
.alert-success{background:#d1fae5;border:1px solid #a7f3d0;color:#065f46}
.alert-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
.table-card{background:var(--card);border-radius:1rem;border:1px solid var(--border);overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.table-header{padding:1rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.table-header h2{font-size:1rem;font-weight:600;color:var(--text)}
table.users-table{width:100%;border-collapse:collapse}
table.users-table thead th{background:#f8fafc;padding:.75rem 1rem;text-align:left;font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:700;border-bottom:2px solid var(--border);position:sticky;top:0;z-index:5;}
table.users-table tbody tr{border-bottom:1px solid var(--border);transition:background .15s}
table.users-table tbody tr:hover{background:#f8fafc}
table.users-table tbody td{padding:.875rem 1rem;vertical-align:middle}
.avatar{width:38px;height:38px;border-radius:50%;font-weight:700;font-size:.8rem;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
.user-info{display:flex;align-items:center;gap:.75rem}
.user-name{font-weight:600;font-size:.875rem;color:var(--text)}
.user-handle{font-size:.75rem;color:var(--muted)}
.role-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.25rem .7rem;border-radius:9999px;font-size:.7rem;font-weight:700}
.role-admin{background:#dbeafe;color:#1d4ed8}.role-proc{background:#dcfce7;color:#15803d}.role-accept{background:#ede9fe;color:#6d28d9}.role-process{background:#fef3c7;color:#b45309}.role-default{background:#f1f5f9;color:#475569}
.perm-pills{display:flex;flex-wrap:wrap;gap:.3rem;max-width:340px}
.perm-pill{display:inline-block;padding:.15rem .5rem;border-radius:4px;font-size:.65rem;font-weight:600;letter-spacing:.03em}
.pp-procurement{background:#dbeafe;color:#1e40af}.pp-acceptance{background:#ede9fe;color:#6b21a8}.pp-processing{background:#fef3c7;color:#92400e}.pp-reporting{background:#fce7f3;color:#9d174d}
.action-btns{display:flex;gap:.4rem}
.act-btn{width:32px;height:32px;border-radius:.4rem;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s}
.act-edit{background:#fef3c7;color:#92400e}.act-edit:hover{background:#fde68a}
.act-delete{background:#fee2e2;color:#991b1b}.act-delete:hover{background:#fca5a5}
.act-suspend{background:#fef3c7;color:#b45309}.act-suspend:hover{background:#fde68a}
.act-unsuspend{background:#d1fae5;color:#065f46}.act-unsuspend:hover{background:#a7f3d0}
.suspended-chip{display:inline-flex;align-items:center;gap:.3rem;padding:.18rem .55rem;border-radius:9999px;font-size:.65rem;font-weight:700;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:1000;display:none;align-items:center;justify-content:center;padding:1rem}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:1.25rem;width:100%;max-width:780px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 60px rgba(0,0,0,.25);animation:slideUp .25s ease}
@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
.modal-head{padding:1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,var(--navy),var(--navy2));border-radius:1.25rem 1.25rem 0 0;position:sticky;top:0;z-index:10}
.modal-head h3{font-family:'Space Grotesk',sans-serif;font-size:1.1rem;font-weight:700;color:#fff}
.modal-close{background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.15s}
.modal-close:hover{background:rgba(255,255,255,.3)}
.modal-body{padding:1.5rem}
.modal-footer{padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:.75rem;background:#f8fafc;border-radius:0 0 1.25rem 1.25rem;position:sticky;bottom:0}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.form-group{display:flex;flex-direction:column;gap:.4rem}
.form-label{font-size:.8rem;font-weight:600;color:var(--text)}
.form-input{border:1.5px solid var(--border);border-radius:.5rem;padding:.6rem .875rem;font-size:.875rem;outline:none;transition:.2s;font-family:inherit;width:100%;box-sizing:border-box}
.form-input:focus{border-color:var(--blue2);box-shadow:0 0 0 3px rgba(37,99,235,.12)}
.form-select{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%2364748b' d='M7 10l5 5 5-5H7z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right .75rem center;padding-right:2rem;cursor:pointer}
.pw-wrap{position:relative}
.pw-toggle{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted)}
.strength-bar{height:4px;border-radius:9999px;background:#e2e8f0;overflow:hidden;margin-top:.35rem}
.strength-fill{height:100%;border-radius:9999px;transition:width .3s,background .3s}
.strength-label{font-size:.7rem;font-weight:600;margin-top:.2rem}
.perm-divider{font-family:'Space Grotesk',sans-serif;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin:1.25rem 0 .75rem;padding-bottom:.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.5rem}
.perm-group{margin-bottom:1rem}
.perm-group-header{display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem;font-size:.8rem;font-weight:700;padding:.4rem .75rem;border-radius:.4rem}
.perm-checks{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:.3rem;padding:.25rem .5rem}
.perm-check-item{display:flex;align-items:center;gap:.5rem;padding:.35rem .5rem;border-radius:.375rem;transition:background .15s;cursor:pointer}
.perm-check-item:hover{background:#f8fafc}
.perm-check-item input[type=checkbox]{width:16px;height:16px;accent-color:var(--blue2);cursor:pointer;flex-shrink:0}
.perm-check-item label{font-size:.8rem;color:var(--text);cursor:pointer}
.check-all-btn{font-size:.7rem;padding:.2rem .6rem;border-radius:4px;border:1.5px solid;cursor:pointer;font-weight:700;transition:.15s;background:transparent;font-family:inherit;margin-left:auto}
@media(max-width:640px){.admin-content{padding:1rem}.form-grid{grid-template-columns:1fr}.admin-hero{padding:1rem}.hero-right{display:none}}
</style>

<!-- HERO BAR -->
<div class="admin-hero">
  <div class="hero-left" style="display:flex;align-items:center;gap:1.25rem;">
    <!-- DTI Logo -->
    <div style="flex-shrink:0;background:rgba(255,255,255,.12);border:2px solid rgba(255,255,255,.25);border-radius:.875rem;padding:.5rem;box-shadow:0 0 18px rgba(255,255,255,.15);">
      <img src="dti.jpg" alt="DTI Logo"
           style="width:56px;height:56px;border-radius:.5rem;display:block;object-fit:cover;">
    </div>
    <div>
      <h1><i class="fas fa-shield-alt mr-2" style="color:#60a5fa"></i>User Administration</h1>
      <p>Manage system users, roles and granular module-level permissions</p>
    </div>
  </div>
  <div class="hero-right">
    <div class="hero-stat">
      <div class="hero-stat-val"><?= $total_users ?></div>
      <div class="hero-stat-lbl">Total Users</div>
    </div>
    <div class="hero-stat">
      <div class="hero-stat-val"><?= $admin_count ?></div>
      <div class="hero-stat-lbl">Admins</div>
    </div>
    <div class="hero-stat">
      <div class="hero-stat-val"><?= $total_users - $admin_count ?></div>
      <div class="hero-stat-lbl">Staff</div>
    </div>
    <a href="index.php" class="btn-primary btn-green">
      <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <a href="audit_trail.php" class="btn-primary" style="background:linear-gradient(135deg,#7c3aed,#6d28d9);box-shadow:0 4px 12px rgba(124,58,237,.35);">
      <i class="fas fa-scroll"></i> Audit Trail
    </a>
  </div>
</div>

<div class="admin-content">
  <?php if ($message): ?>
  <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
    <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?> fa-lg"></i>
    <span><?= $message ?></span>
  </div>
  <?php endif; ?>

  <!-- Toolbar -->
  <div class="toolbar">
    <form method="GET" style="display:flex;gap:.5rem;flex:1;flex-wrap:wrap;">
      <div class="search-box">
        <i class="fas fa-search" style="color:var(--muted)"></i>
        <input type="text" name="search" placeholder="Search by name, username, or role…"
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <button type="submit" class="btn-primary" style="padding:.5rem .875rem;"><i class="fas fa-search"></i></button>
      <?php if ($search): ?>
        <a href="admin.php" class="btn-cancel"><i class="fas fa-times fa-sm"></i> Clear</a>
      <?php endif; ?>
    </form>
    <button class="btn-primary" onclick="openModal()">
      <i class="fas fa-user-plus"></i> Add New User
    </button>
  </div>

  <!-- Table -->
  <div class="table-card">
    <div class="table-header">
      <h2><i class="fas fa-users mr-2" style="color:var(--blue2)"></i>System Users
        <span style="font-size:.75rem;color:var(--muted);font-weight:400;margin-left:.5rem;">— <?= $total_users ?> record<?= $total_users !== 1 ? 's' : '' ?></span>
      </h2>
    </div>
    <div style="overflow-x:auto;overflow-y:auto;max-height:560px;">
    <table class="users-table">
      <thead>
        <tr>
          <th width="40">#</th>
          <th>User</th>
          <th>Role / Department</th>
          <th>Module Permissions</th>
          <th>Registered</th>
          <th width="80">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($users): foreach ($users as $i => $u):
          $perms = json_decode($u['permissions'] ?? '[]', true) ?: [];
          $is_self = ($u['id'] == $_SESSION['user_id']);
          $avatarColors = ['#2563eb','#7c3aed','#059669','#dc2626','#0891b2','#d97706'];
          $aColor = $avatarColors[$u['id'] % count($avatarColors)];
          $nameParts = explode(' ', $u['name']);
          $initials = strtoupper(substr($nameParts[0],0,1).(isset($nameParts[1])?substr($nameParts[1],0,1):''));
          $roleBadge = match($u['role']) {
            'Administrator'       => ['class'=>'role-admin',  'icon'=>'fa-crown'],
            'Procurement Section' => ['class'=>'role-proc',   'icon'=>'fa-file-alt'],
            'Acceptance Section'  => ['class'=>'role-accept', 'icon'=>'fa-clipboard-check'],
            'Processing Section'  => ['class'=>'role-process','icon'=>'fa-money-check-alt'],
            default               => ['class'=>'role-default','icon'=>'fa-user'],
          };
          // Group displayed permission pills
          $perm_display = [];
          foreach ($perm_groups as $gname => $gdata) {
            $cssClass = match($gname) {
              'Procurement'=>'pp-procurement','Acceptance'=>'pp-acceptance',
              'Processing'=>'pp-processing','Reporting'=>'pp-reporting',default=>'pp-procurement'
            };
            foreach (array_keys($gdata['perms']) as $pKey) {
              if (in_array($pKey,$perms))
                $perm_display[] = ['label'=>$gdata['perms'][$pKey],'class'=>$cssClass];
            }
          }
        ?>
        <tr>
          <td style="color:var(--muted);font-size:.8rem;text-align:center;"><?= $i+1 ?></td>
          <td>
            <div class="user-info">
              <div class="avatar" style="background:<?= $aColor ?>1a;color:<?= $aColor ?>">
                <?= $initials ?: strtoupper(substr($u['username'],0,2)) ?>
              </div>
              <div>
                <div class="user-name">
                  <?= htmlspecialchars($u['name']) ?>
                  <?php if ($is_self): ?>
                    <span style="font-size:.65rem;background:#dbeafe;color:#1d4ed8;padding:.1rem .4rem;border-radius:4px;margin-left:.3rem;">You</span>
                  <?php endif; ?>
                  <?php if (!empty($u['is_suspended'])): ?>
                    <span class="suspended-chip"><i class="fas fa-ban fa-xs"></i> Suspended</span>
                  <?php endif; ?>
                </div>
                <div class="user-handle">@<?= htmlspecialchars($u['username']) ?>
                  <?php if (!empty($u['suspended_reason'])): ?>
                    <span style="color:#ef4444;font-size:.68rem;font-style:italic;"> — <?= htmlspecialchars($u['suspended_reason']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </td>
          <td>
            <span class="role-badge <?= $roleBadge['class'] ?>">
              <i class="fas <?= $roleBadge['icon'] ?> fa-xs"></i>
              <?= htmlspecialchars($u['role']) ?>
            </span>
          </td>
          <td>
            <?php if ($u['role'] === 'Administrator'): ?>
              <span style="font-size:.75rem;color:var(--muted);font-style:italic;">
                <i class="fas fa-infinity mr-1" style="color:var(--gold)"></i>Full Access — All Modules
              </span>
            <?php elseif (!empty($perm_display)): ?>
              <div class="perm-pills">
                <?php foreach ($perm_display as $pd): ?>
                  <span class="perm-pill <?= $pd['class'] ?>"><?= htmlspecialchars($pd['label']) ?></span>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <span style="font-size:.75rem;color:#ef4444;font-style:italic;">
                <i class="fas fa-lock mr-1"></i>No permissions assigned
              </span>
            <?php endif; ?>
          </td>
          <td style="font-size:.8rem;color:var(--muted);white-space:nowrap;"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
          <td>
            <div class="action-btns">
              <button class="act-btn act-edit" title="Edit User"
                      onclick='openEditModal(<?= json_encode(['id'=>$u['id'],'name'=>$u['name'],'username'=>$u['username'],'role'=>$u['role'],'permissions'=>$u['permissions']]) ?>)'>
                <i class="fas fa-pen fa-xs"></i>
              </button>
              <?php if (!$is_self): ?>
                <?php if (empty($u['is_suspended'])): ?>
                  <button class="act-btn act-suspend" title="Suspend User"
                          onclick="confirmSuspend(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                    <i class="fas fa-ban fa-xs"></i>
                  </button>
                <?php else: ?>
                  <a href="?unsuspend=<?= $u['id'] ?>"
                     title="Restore Account"
                     onclick="return confirm('Restore account for <?= htmlspecialchars(addslashes($u['name'])) ?>?')"
                     class="act-btn act-unsuspend">
                    <i class="fas fa-check fa-xs"></i>
                  </a>
                <?php endif; ?>
                <a href="?delete=<?= $u['id'] ?>"
                   title="Delete User"
                   onclick="return confirm('Permanently delete <?= htmlspecialchars(addslashes($u['name'])) ?>?')"
                   class="act-btn act-delete">
                  <i class="fas fa-trash fa-xs"></i>
                </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--muted);font-style:italic;">No users found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

<!-- MODAL -->
<div class="modal-overlay" id="userModal">
  <div class="modal-box">
    <div class="modal-head">
      <h3 id="modalTitle"><i class="fas fa-user-plus mr-2" style="color:#93c5fd"></i>Add New User</h3>
      <button class="modal-close" onclick="closeModal()"><i class="fas fa-times fa-sm"></i></button>
    </div>

    <form method="POST" id="userForm">
      <div class="modal-body">
        <input type="hidden" name="action" id="formAction" value="create">
        <input type="hidden" name="user_id" id="formUserId">

        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Full Name / Designation <span style="color:red">*</span></label>
            <input type="text" name="name" id="fName" class="form-input" placeholder="e.g. Juan dela Cruz" required>
          </div>
          <div class="form-group">
            <label class="form-label">Username <span style="color:red">*</span></label>
            <input type="text" name="username" id="fUsername" class="form-input" placeholder="e.g. jdelacruz" required autocomplete="off">
          </div>
          <div class="form-group">
            <label class="form-label" id="pwLabel">Password <span style="color:red">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="password" id="fPassword" class="form-input"
                     placeholder="Enter a strong password" autocomplete="new-password"
                     style="padding-right:2.5rem" oninput="checkStrength(this.value)">
              <button type="button" class="pw-toggle" onclick="togglePw()" tabindex="-1">
                <i class="fas fa-eye fa-sm" id="pwEye"></i>
              </button>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="strengthFill" style="width:0%"></div></div>
            <div class="strength-label" id="strengthLabel" style="color:var(--muted)"></div>
          </div>
          <div class="form-group" id="deptFieldGroup">
            <label class="form-label">Department / Section <span style="color:red">*</span></label>
            <select name="role" id="fRole" class="form-input form-select" required onchange="autofillPerms(this.value)">
              <option value="">— Select Department —</option>
              <option value="Procurement Section">Procurement Section</option>
              <option value="Acceptance Section">Acceptance Section</option>
              <option value="Processing Section">Processing Section</option>
            </select>
          </div>
        </div>

        <!-- Permissions -->
        <div id="permSection">
        <div class="perm-divider" style="margin-top:1.5rem;">
          <i class="fas fa-key"></i> Module Access Permissions
        </div>

        <?php foreach ($perm_groups as $gname => $gdata):
          $gIcon  = $gdata['icon'];
          $gColor = $gdata['color'];
          $gKey   = strtolower($gname);
          $bgLight = $gColor . '18';
        ?>
        <div class="perm-group">
          <div class="perm-group-header" style="background:<?= $bgLight ?>;color:<?= $gColor ?>">
            <i class="fas <?= $gIcon ?> fa-sm"></i>
            <strong><?= $gname ?></strong>
            <button type="button" class="check-all-btn"
                    style="color:<?= $gColor ?>;border-color:<?= $gColor ?>"
                    onclick="toggleGroup('<?= $gKey ?>')">Check All</button>
          </div>
          <div class="perm-checks">
            <?php foreach ($gdata['perms'] as $pKey => $pLabel): ?>
            <div class="perm-check-item">
              <input type="checkbox" name="perm_<?= $pKey ?>" id="perm_<?= $pKey ?>"
                     class="perm-cb group-<?= $gKey ?>" value="1">
              <label for="perm_<?= $pKey ?>"><?= $pLabel ?></label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        </div><!-- /permSection -->

        <!-- Admin notice: shown only when editing an Administrator -->
        <div id="adminPermNotice" style="display:none; margin-top:1.25rem;">
          <div style="background:linear-gradient(135deg,#1e3a5f,#1e293b);border:1px solid #3b5a8a;border-radius:.75rem;padding:1.1rem 1.25rem;display:flex;align-items:center;gap:.875rem;">
            <div style="width:42px;height:42px;background:rgba(251,191,36,.15);border-radius:.5rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fas fa-infinity" style="color:#fbbf24;font-size:1.1rem;"></i>
            </div>
            <div>
              <div style="color:#e2e8f0;font-weight:700;font-size:.875rem;margin-bottom:.2rem;">
                <i class="fas fa-crown" style="color:#fbbf24;margin-right:.35rem;"></i>Administrator — Unrestricted Full Access
              </div>
              <div style="color:#93c5fd;font-size:.78rem;line-height:1.5;">
                This account has <strong style="color:#e2e8f0;">complete access to all system modules</strong>. Department and module permission settings are not applicable to Administrator accounts.
              </div>
            </div>
          </div>
        </div>

      </div><!-- /modal-body -->

      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeModal()">
          <i class="fas fa-times fa-sm"></i> Cancel
        </button>
        <button type="submit" class="btn-primary">
          <i class="fas fa-save fa-sm"></i> <span id="submitLabel">Create User</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const groupStates = {};

function openModal() {
  document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus mr-2" style="color:#93c5fd"></i>Add New User';
  document.getElementById('submitLabel').textContent = 'Create User';
  document.getElementById('formAction').value = 'create';
  document.getElementById('formUserId').value = '';
  document.getElementById('userForm').reset();
  document.getElementById('pwLabel').innerHTML = 'Password <span style="color:red">*</span>';
  document.getElementById('fPassword').required = true;
  resetStrength();
  document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = false);
  // Always show dept + perms, hide admin notice on create
  var deptGroup   = document.getElementById('deptFieldGroup');
  var permSection = document.getElementById('permSection');
  var adminNotice = document.getElementById('adminPermNotice');
  var fRoleEl     = document.getElementById('fRole');
  if (deptGroup)   deptGroup.style.display   = '';
  if (permSection) permSection.style.display  = '';
  if (adminNotice) adminNotice.style.display  = 'none';
  if (fRoleEl)     fRoleEl.required = true;
  document.getElementById('userModal').classList.add('open');
}

function openEditModal(user) {
  document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit mr-2" style="color:#93c5fd"></i>Edit User';
  document.getElementById('submitLabel').textContent = 'Save Changes';
  document.getElementById('formAction').value = 'edit';
  document.getElementById('formUserId').value = user.id;
  document.getElementById('fName').value = user.name;
  document.getElementById('fUsername').value = user.username;
  document.getElementById('fPassword').value = '';
  document.getElementById('fPassword').required = false;
  document.getElementById('pwLabel').innerHTML = 'New Password <span style="color:var(--muted);font-weight:400;font-size:.7rem;">(leave blank to keep current)</span>';
  resetStrength();

  // Determine if this is an Administrator account
  var isAdmin = (user.role === 'Administrator');

  // Show/hide dept field and permissions section based on role
  var deptGroup    = document.getElementById('deptFieldGroup');
  var permSection  = document.getElementById('permSection');
  var adminNotice  = document.getElementById('adminPermNotice');
  var fRoleEl      = document.getElementById('fRole');

  if (isAdmin) {
    // Hide dept selector and permission checkboxes for admin
    if (deptGroup)   deptGroup.style.display   = 'none';
    if (permSection) permSection.style.display  = 'none';
    if (adminNotice) adminNotice.style.display  = 'block';
    // Set a hidden role value so it still submits correctly
    if (fRoleEl) { fRoleEl.value = 'Administrator'; fRoleEl.required = false; }
  } else {
    if (deptGroup)   deptGroup.style.display   = '';
    if (permSection) permSection.style.display  = '';
    if (adminNotice) adminNotice.style.display  = 'none';
    if (fRoleEl) { fRoleEl.value = user.role; fRoleEl.required = true; }
  }

  // Load existing permissions into checkboxes (only relevant for non-admin)
  let perms = [];
  try { perms = JSON.parse(user.permissions || '[]'); } catch(e){}
  document.querySelectorAll('.perm-cb').forEach(cb => {
    cb.checked = perms.includes(cb.name.replace('perm_',''));
  });

  document.getElementById('userModal').classList.add('open');
}

function closeModal() {
  document.getElementById('userModal').classList.remove('open');
}

document.getElementById('userModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

function toggleGroup(gKey) {
  const cbs = document.querySelectorAll('.group-' + gKey);
  groupStates[gKey] = !groupStates[gKey];
  cbs.forEach(cb => cb.checked = groupStates[gKey]);
  const btn = document.querySelector(`[onclick="toggleGroup('${gKey}')"]`);
  if (btn) btn.textContent = groupStates[gKey] ? 'Uncheck All' : 'Check All';
}

function togglePw() {
  const inp = document.getElementById('fPassword');
  const eye = document.getElementById('pwEye');
  inp.type = inp.type === 'password' ? 'text' : 'password';
  eye.className = inp.type === 'text' ? 'fas fa-eye-slash fa-sm' : 'fas fa-eye fa-sm';
}

function checkStrength(pw) {
  const fill = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  if (!pw) { resetStrength(); return; }
  let score = 0;
  if (pw.length >= 8) score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw)) score++;
  if (/[0-9]/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  const levels = [
    {pct:20,color:'#ef4444',text:'Very Weak'},
    {pct:40,color:'#f97316',text:'Weak'},
    {pct:60,color:'#f59e0b',text:'Fair'},
    {pct:80,color:'#84cc16',text:'Strong'},
    {pct:100,color:'#10b981',text:'Very Strong 💪'},
  ];
  const lvl = levels[Math.min(score-1, 4)];
  fill.style.width = (lvl ? lvl.pct : 20) + '%';
  fill.style.background = lvl ? lvl.color : '#ef4444';
  label.textContent = lvl ? lvl.text : 'Very Weak';
  label.style.color = lvl ? lvl.color : '#ef4444';
}

function resetStrength() {
  document.getElementById('strengthFill').style.width = '0%';
  document.getElementById('strengthLabel').textContent = '';
}

// Auto-suggest permissions when selecting department (only on Create)
function autofillPerms(role) {
  if (document.getElementById('formAction').value !== 'create') return;
  const presets = {
    'Procurement Section': ['purchase_request','payroll','quotations','purchase_orders','cancelled_pr','sub_pr'],
    'Acceptance Section':  ['iar'],
    'Processing Section':  ['disbursement','payroll_dv'],
  };
  document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = false);
  (presets[role] || []).forEach(pKey => {
    const el = document.getElementById('perm_' + pKey);
    if (el) el.checked = true;
  });
}

// ── Suspend confirmation with reason ──────────────────────────────────────────
function confirmSuspend(userId, userName) {
  document.getElementById('suspendUserId').value   = userId;
  document.getElementById('suspendUserName').textContent = userName;
  document.getElementById('suspendReason').value   = '';
  document.getElementById('suspendModal').classList.add('open');
}
function closeSuspendModal() {
  document.getElementById('suspendModal').classList.remove('open');
}
function submitSuspend() {
  const id     = document.getElementById('suspendUserId').value;
  const reason = encodeURIComponent(document.getElementById('suspendReason').value.trim() || 'Suspended by Administrator');
  window.location.href = `?suspend=${id}&reason=${reason}`;
}
document.getElementById('suspendModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeSuspendModal();
});
</script>

<!-- ── Suspend Modal ── -->
<div class="modal-overlay" id="suspendModal">
  <div class="modal-box" style="max-width:420px;">
    <div class="modal-head" style="background:linear-gradient(135deg,#7c2d12,#b45309);">
      <h3><i class="fas fa-ban mr-2" style="color:#fcd34d"></i>Suspend User</h3>
      <button class="modal-close" onclick="closeSuspendModal()"><i class="fas fa-times fa-sm"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="suspendUserId">
      <p style="font-size:.875rem;color:var(--text);margin-bottom:1rem;">
        You are about to suspend <strong id="suspendUserName"></strong>.<br>
        <span style="color:var(--muted);font-size:.8rem;">The user will not be able to log in until their account is restored.</span>
      </p>
      <div class="form-group">
        <label class="form-label">Reason for Suspension <span style="color:var(--muted);font-weight:400;">(optional)</span></label>
        <input type="text" id="suspendReason" class="form-input"
               placeholder="e.g. Unauthorized access attempt, Temporary leave…">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" onclick="closeSuspendModal()"><i class="fas fa-times fa-sm"></i> Cancel</button>
      <button onclick="submitSuspend()"
              style="display:inline-flex;align-items:center;gap:.5rem;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;font-weight:600;font-size:.875rem;padding:.6rem 1.25rem;border-radius:.625rem;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(220,38,38,.35);">
        <i class="fas fa-ban fa-sm"></i> Suspend Account
      </button>
    </div>
  </div>
</div>


<?php include 'inc/footer.php'; ?>
