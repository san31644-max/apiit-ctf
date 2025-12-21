<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

/* =======================
   ADMIN ONLY
======================= */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$success = $error = '';
$admin_id = (int)($_SESSION['user_id'] ?? 0);

/* =======================
   HANDLE POST
======================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ===== ADD USER ===== */
    if (($_POST['action'] ?? '') === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = $_POST['role'] ?? 'user';

        if ($username && $password && in_array($role, ['admin','user'], true)) {
            try {
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $check->execute([$username]);

                if ($check->fetch()) {
                    $error = "Username already exists.";
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $hash, $role]);
                    $success = "User '{$username}' added successfully!";
                }
            } catch (PDOException $e) {
                $error = "Failed to add user.";
            }
        } else {
            $error = "Please fill all fields correctly.";
        }
    }

    /* ===== EDIT USER ===== */
    if (($_POST['action'] ?? '') === 'edit_user') {
        $id       = (int)($_POST['edit_user_id'] ?? 0);
        $username = trim($_POST['edit_username'] ?? '');
        $role     = $_POST['edit_role'] ?? 'user';
        $password = trim($_POST['edit_password'] ?? '');

        if ($id <= 0 || !$username || !in_array($role, ['admin','user'], true)) {
            $error = "Invalid edit request.";
        } else {
            try {
                // Prevent duplicate usernames (except same user)
                $check = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
                $check->execute([$username, $id]);

                if ($check->fetch()) {
                    $error = "Username already exists.";
                } else {
                    if ($password !== '') {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ?, password = ? WHERE id = ?");
                        $stmt->execute([$username, $role, $hash, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
                        $stmt->execute([$username, $role, $id]);
                    }
                    $success = "User updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Failed to update user.";
            }
        }
    }

    /* ===== DELETE USER ===== */
    if (($_POST['action'] ?? '') === 'delete_user') {
        $delete_id = (int)($_POST['delete_user_id'] ?? 0);

        if ($delete_id <= 0) {
            $error = "Invalid delete request.";
        } elseif ($delete_id === $admin_id) {
            $error = "You can't delete your own account.";
        } else {
            try {
                // Never delete admins
                $roleCheck = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                $roleCheck->execute([$delete_id]);
                $row = $roleCheck->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $error = "User not found.";
                } elseif (($row['role'] ?? '') === 'admin') {
                    $error = "Admin users cannot be deleted.";
                } else {
                    $pdo->beginTransaction();

                    $pdo->prepare("DELETE FROM hint_views WHERE user_id=?")->execute([$delete_id]);
                    $pdo->prepare("DELETE FROM solves WHERE user_id=?")->execute([$delete_id]);
                    $pdo->prepare("DELETE FROM user_activity WHERE user_id=?")->execute([$delete_id]);
                    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$delete_id]);

                    $pdo->commit();
                    $success = "User deleted successfully!";
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = "Failed to delete user.";
            }
        }
    }
}

/* =======================
   FETCH USERS
======================= */
$stmt = $pdo->query("SELECT id, username, role, score, created_at, last_login FROM users ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Manage Users — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>

<style>
  :root{
    --bg:#05070b;
    --panel: rgba(10, 16, 28, .78);
    --stroke: rgba(45,226,138,.20);
    --stroke2: rgba(45,226,138,.40);
    --text:#eafaf3;
    --muted: rgba(234,250,243,.65);
    --accent:#2de28a;
    --danger:#ff5b5b;
  }
  body{
    background:
      radial-gradient(900px 500px at 20% 10%, rgba(45,226,138,.16), transparent 60%),
      radial-gradient(900px 500px at 90% 25%, rgba(71,215,255,.10), transparent 60%),
      radial-gradient(900px 500px at 50% 90%, rgba(255,102,217,.08), transparent 65%),
      var(--bg);
    color: var(--text);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  }
  .glass{
    background: var(--panel);
    border: 1px solid var(--stroke);
    box-shadow: 0 18px 60px rgba(0,0,0,.55);
    backdrop-filter: blur(14px);
  }
  .chip{
    border: 1px solid var(--stroke);
    background: rgba(255,255,255,.04);
  }
  .input{
    background: rgba(255,255,255,.04);
    border: 1px solid var(--stroke);
    color: var(--text);
    outline: none;
  }
  .input:focus{
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(45,226,138,.15);
  }

  .btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    border-radius: 14px;
    padding: 10px 14px;
    border: 1px solid var(--stroke2);
    background: rgba(255,255,255,.04);
    transition: .15s ease;
    white-space: nowrap;
  }
  .btn:hover{
    transform: translateY(-1px);
    background: rgba(45,226,138,.10);
    border-color: rgba(45,226,138,.75);
  }
  .btn-primary{
    background: linear-gradient(135deg, rgba(45,226,138,.95), rgba(45,226,138,.55));
    border: 0;
    color: #06120c;
    font-weight: 950;
  }
  .btn-primary:hover{ filter: brightness(1.03); }
  .btn-danger{
    background: linear-gradient(135deg, rgba(255,91,91,.95), rgba(255,91,91,.55));
    border: 0;
    color: #1a0505;
    font-weight: 950;
  }

  table{ width:100%; border-collapse: collapse; }
  th, td{
    padding: 14px 14px;
    border-bottom: 1px solid rgba(45,226,138,.16);
    text-align:left;
    white-space: nowrap;
    vertical-align: middle;
  }
  th{
    color: rgba(234,250,243,.7);
    text-transform: uppercase;
    letter-spacing: .2em;
    font-size: 12px;
  }
  tr:hover td{ background: rgba(45,226,138,.05); }

  .modal-backdrop{
    background: rgba(0,0,0,.70);
    backdrop-filter: blur(8px);
  }
</style>
</head>

<body class="min-h-screen">

<div class="max-w-7xl mx-auto p-4 md:p-8">

  <!-- Header -->
  <div class="glass rounded-3xl p-6 md:p-8 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <div class="text-xs tracking-[0.35em] text-[rgba(234,250,243,.65)]">ADMIN PANEL</div>
        <h1 class="text-3xl md:text-4xl font-extrabold text-green-300 mt-2">Manage Users</h1>
        <p class="text-sm text-[rgba(234,250,243,.65)] mt-2">Edit usernames, roles, and reset passwords safely.</p>
      </div>
      <div class="chip rounded-2xl px-4 py-3 text-sm">
        Logged in as <span class="text-green-300 font-extrabold">admin</span>
      </div>
    </div>
  </div>

  <!-- Alerts -->
  <?php if($success): ?>
    <div class="glass rounded-2xl p-4 mb-5 border border-[rgba(45,226,138,.35)]">
      <div class="text-green-300 font-extrabold">✅ Success</div>
      <div class="text-sm text-[rgba(234,250,243,.75)] mt-1"><?= htmlspecialchars($success) ?></div>
    </div>
  <?php endif; ?>

  <?php if($error): ?>
    <div class="glass rounded-2xl p-4 mb-5 border border-[rgba(255,91,91,.35)]">
      <div class="text-red-300 font-extrabold">⚠️ Error</div>
      <div class="text-sm text-[rgba(234,250,243,.75)] mt-1"><?= htmlspecialchars($error) ?></div>
    </div>
  <?php endif; ?>

  <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

    <!-- Add User -->
    <section class="glass rounded-3xl p-6 xl:col-span-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-extrabold text-green-300">➕ Add User</h2>
        <span class="chip rounded-xl px-3 py-2 text-xs text-[rgba(234,250,243,.7)]">Create</span>
      </div>

      <form method="POST" class="mt-5 space-y-4">
        <input type="hidden" name="action" value="add_user" />

        <div>
          <label class="text-xs text-[rgba(234,250,243,.65)]">Username</label>
          <input class="input w-full rounded-2xl px-4 py-3 mt-1" name="username" required placeholder="player01" />
        </div>

        <div>
          <label class="text-xs text-[rgba(234,250,243,.65)]">Password</label>
          <div class="flex gap-2 mt-1">
            <input id="addPw" class="input w-full rounded-2xl px-4 py-3" type="password" name="password" required placeholder="Set a password" />
            <button type="button" class="btn" onclick="togglePw('addPw', this)">Show</button>
          </div>
          <div class="text-xs text-[rgba(234,250,243,.55)] mt-2">Passwords are stored hashed (not viewable).</div>
        </div>

        <div>
          <label class="text-xs text-[rgba(234,250,243,.65)]">Role</label>
          <select class="input w-full rounded-2xl px-4 py-3 mt-1" name="role" required>
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <button class="btn-primary w-full rounded-2xl px-4 py-3">Add User</button>
      </form>
    </section>

    <!-- Users Table -->
    <section class="glass rounded-3xl p-6 xl:col-span-8">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <h2 class="text-lg font-extrabold text-green-300">👥 Users</h2>
          <p class="text-xs text-[rgba(234,250,243,.6)] mt-1">Edit/Delete buttons are in the Action column.</p>
        </div>
        <input id="searchBox" class="input rounded-2xl px-4 py-3 w-full md:w-80"
               placeholder="Search username / role / id..." />
      </div>

      <div class="mt-5 overflow-x-auto rounded-2xl border border-[rgba(45,226,138,.18)]">
        <table id="usersTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Role</th>
              <th>Score</th>
              <th>Created</th>
              <th>Last Login</th>
              <th style="min-width: 260px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($users as $u): ?>
              <?php
                $uid = (int)$u['id'];
                $role = $u['role'] ?? 'user';
                $isAdmin = ($role === 'admin');
                $isSelf = ($uid === $admin_id);
              ?>
              <tr class="user-row"
                  data-id="<?= $uid ?>"
                  data-username="<?= htmlspecialchars(strtolower($u['username'] ?? ''), ENT_QUOTES) ?>"
                  data-role="<?= htmlspecialchars(strtolower($role), ENT_QUOTES) ?>">
                <td><?= $uid ?></td>
                <td class="font-extrabold"><?= htmlspecialchars($u['username'] ?? '') ?></td>
                <td>
                  <?php if($isAdmin): ?>
                    <span class="chip rounded-xl px-3 py-2 text-xs text-green-300">admin</span>
                  <?php else: ?>
                    <span class="chip rounded-xl px-3 py-2 text-xs text-[rgba(234,250,243,.7)]">user</span>
                  <?php endif; ?>
                </td>
                <td class="text-green-300 font-extrabold"><?= (int)($u['score'] ?? 0) ?></td>
                <td class="text-xs text-[rgba(234,250,243,.65)]"><?= htmlspecialchars($u['created_at'] ?? '-') ?></td>
                <td class="text-xs text-[rgba(234,250,243,.65)]"><?= htmlspecialchars($u['last_login'] ?? '-') ?></td>

                <!-- IMPORTANT: Use normal block layout inside td (NOT flex on td) -->
                <td>
                  <div class="flex flex-wrap gap-2 items-center">
                    <!-- EDIT -->
                    <button type="button"
                      class="btn"
                      onclick="openEditModal(
                        <?= $uid ?>,
                        '<?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES) ?>',
                        '<?= htmlspecialchars($role, ENT_QUOTES) ?>'
                      )">✏️ Edit</button>

                    <!-- DELETE -->
                    <?php if(!$isAdmin && !$isSelf): ?>
                      <form method="POST" onsubmit="return confirm('Delete this user permanently?');">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="delete_user_id" value="<?= $uid ?>">
                        <button class="btn-danger">🗑 Delete</button>
                      </form>
                    <?php else: ?>
                      <span class="chip rounded-xl px-3 py-2 text-xs text-[rgba(234,250,243,.6)]">Protected</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div id="noResults" class="hidden mt-4 text-sm text-[rgba(234,250,243,.65)] text-center">
        No matching users.
      </div>
    </section>
  </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="fixed inset-0 hidden items-center justify-center modal-backdrop z-50 p-4">
  <div class="glass w-full max-w-xl rounded-3xl overflow-hidden border border-[rgba(45,226,138,.25)]">
    <div class="p-6 border-b border-[rgba(45,226,138,.15)] flex items-center justify-between">
      <div>
        <div class="text-xs tracking-[0.35em] text-[rgba(234,250,243,.65)]">EDIT USER</div>
        <div class="text-2xl font-extrabold text-green-300 mt-1">Update account</div>
      </div>
      <button class="btn" type="button" onclick="closeEditModal()">✖</button>
    </div>

    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="edit_user_id" id="edit_user_id">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="text-xs text-[rgba(234,250,243,.65)]">Username</label>
          <input class="input w-full rounded-2xl px-4 py-3 mt-1"
                 name="edit_username" id="edit_username" required>
        </div>

        <div>
          <label class="text-xs text-[rgba(234,250,243,.65)]">Role</label>
          <select class="input w-full rounded-2xl px-4 py-3 mt-1"
                  name="edit_role" id="edit_role" required>
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>

      <div>
        <label class="text-xs text-[rgba(234,250,243,.65)]">Reset Password (optional)</label>
        <div class="flex gap-2 mt-1">
          <input id="editPw" class="input w-full rounded-2xl px-4 py-3"
                 type="password" name="edit_password"
                 placeholder="Leave blank to keep current password">
          <button type="button" class="btn" onclick="togglePw('editPw', this)">Show</button>
        </div>
        <div class="text-xs text-[rgba(234,250,243,.55)] mt-2">
          Existing passwords can’t be viewed (stored hashed). This sets a new one.
        </div>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
        <button class="btn-primary rounded-2xl px-5 py-3">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
  function togglePw(id, btn){
    const input = document.getElementById(id);
    if (!input) return;
    if (input.type === "password") { input.type = "text"; btn.textContent = "Hide"; }
    else { input.type = "password"; btn.textContent = "Show"; }
  }

  // Search
  const searchBox = document.getElementById('searchBox');
  const rows = Array.from(document.querySelectorAll('.user-row'));
  const noResults = document.getElementById('noResults');

  function applyFilter(){
    const q = (searchBox?.value || '').trim().toLowerCase();
    let visible = 0;
    rows.forEach(r => {
      const id = (r.dataset.id || '');
      const u = (r.dataset.username || '');
      const role = (r.dataset.role || '');
      const match = !q || id.includes(q) || u.includes(q) || role.includes(q);
      r.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    noResults.classList.toggle('hidden', visible !== 0);
  }
  if (searchBox) searchBox.addEventListener('input', applyFilter);

  // Modal
  const modal = document.getElementById('editModal');

  function openEditModal(id, username, role){
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;

    const pw = document.getElementById('editPw');
    if (pw) pw.value = '';

    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeEditModal(){
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeEditModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeEditModal();
  });
</script>

</body>
</html>
