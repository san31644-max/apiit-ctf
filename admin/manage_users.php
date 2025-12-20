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

    /* =======================
       ADD USER
    ======================== */
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = $_POST['role'] ?? 'user';

        if ($username && $password && in_array($role, ['admin', 'user'], true)) {
            try {
                // Optional: check duplicate username
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

    /* =======================
       EDIT USER
    ======================== */
    if (isset($_POST['action']) && $_POST['action'] === 'edit_user') {
        $id       = (int)($_POST['edit_user_id'] ?? 0);
        $username = trim($_POST['edit_username'] ?? '');
        $role     = $_POST['edit_role'] ?? 'user';
        $password = trim($_POST['edit_password'] ?? '');

        if ($id <= 0 || !$username || !in_array($role, ['admin', 'user'], true)) {
            $error = "Invalid edit request.";
        } else {
            try {
                // Optional: prevent changing your own role if you want
                // if ($id === $admin_id && $role !== 'admin') { $error = "You can't change your own role."; }

                // prevent duplicate usernames (except same user)
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

    /* =======================
       DELETE USER
    ======================== */
    if (isset($_POST['action']) && $_POST['action'] === 'delete_user') {
        $delete_id = (int)($_POST['delete_user_id'] ?? 0);

        if ($delete_id <= 0) {
            $error = "Invalid delete request.";
        } elseif ($delete_id === $admin_id) {
            $error = "You can't delete your own account.";
        } else {
            try {
                // Don’t delete admins
                $roleCheck = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                $roleCheck->execute([$delete_id]);
                $row = $roleCheck->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $error = "User not found.";
                } elseif (($row['role'] ?? '') === 'admin') {
                    $error = "Admin users cannot be deleted.";
                } else {
                    $pdo->beginTransaction();

                    $pdo->prepare("DELETE FROM hint_views WHERE user_id = ?")->execute([$delete_id]);
                    $pdo->prepare("DELETE FROM solves WHERE user_id = ?")->execute([$delete_id]);
                    $pdo->prepare("DELETE FROM user_activity WHERE user_id = ?")->execute([$delete_id]);

                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$delete_id]);

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
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Manage Users — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>

<style>
  :root{
    --bg:#070a0f;
    --panel: rgba(9, 14, 20, .72);
    --stroke: rgba(45,226,138,.22);
    --stroke-strong: rgba(45,226,138,.38);
    --text: #c9f7e4;
    --muted: rgba(201,247,228,.65);
    --accent:#2de28a;
    --danger:#ff5b5b;
  }
  body {
    background:
      radial-gradient(900px 500px at 20% 10%, rgba(45,226,138,.14), transparent 65%),
      radial-gradient(900px 500px at 90% 30%, rgba(70,160,255,.10), transparent 60%),
      radial-gradient(900px 500px at 50% 90%, rgba(255,90,200,.08), transparent 65%),
      var(--bg);
    color: var(--text);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  }
  .glass {
    background: var(--panel);
    border: 1px solid var(--stroke);
    box-shadow: 0 10px 30px rgba(0,0,0,.35);
    backdrop-filter: blur(10px);
  }
  .chip {
    border: 1px solid var(--stroke);
    background: rgba(255,255,255,.04);
  }
  .input {
    background: rgba(255,255,255,.04);
    border: 1px solid var(--stroke);
    color: var(--text);
    outline: none;
  }
  .input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(45,226,138,.15);
  }
  .btn {
    border: 1px solid var(--stroke-strong);
    background: rgba(255,255,255,.04);
    transition: .15s ease;
  }
  .btn:hover {
    transform: translateY(-1px);
    border-color: rgba(45,226,138,.65);
    background: rgba(45,226,138,.10);
  }
  .btn-primary {
    background: linear-gradient(135deg, rgba(45,226,138,.95), rgba(45,226,138,.55));
    color: #06120c;
    border: 0;
  }
  .btn-primary:hover { filter: brightness(1.03); background: linear-gradient(135deg, rgba(45,226,138,1), rgba(45,226,138,.62)); }
  .btn-danger {
    background: linear-gradient(135deg, rgba(255,91,91,.95), rgba(255,91,91,.55));
    color: #1a0505;
    border: 0;
  }
  .btn-danger:hover { filter: brightness(1.03); }
  .link {
    display:block;
    padding: 12px 14px;
    border-bottom: 1px solid rgba(255,255,255,.05);
    color: var(--text);
  }
  .link:hover { background: rgba(45,226,138,.08); color: var(--accent); }
  .table-wrap { overflow-x: auto; }
  table { width:100%; border-collapse: collapse; }
  th, td { padding: 12px 12px; border-bottom: 1px solid rgba(45,226,138,.18); text-align:left; white-space: nowrap; }
  th { color: var(--accent); font-weight: 700; letter-spacing: .06em; text-transform: uppercase; font-size: .75rem; }
  tr:hover td { background: rgba(45,226,138,.05); }
  .modal-backdrop { background: rgba(0,0,0,.65); backdrop-filter: blur(6px); }
</style>
</head>

<body class="min-h-screen">

<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside class="w-72 glass hidden md:block">
    <div class="p-5 border-b border-[rgba(45,226,138,.18)]">
      <div class="text-green-300 text-xs tracking-widest">ADMIN PANEL</div>
      <div class="text-2xl font-extrabold text-green-400">APIIT CTF</div>
      <div class="text-xs text-[rgba(201,247,228,.6)] mt-1">Users / Roles / Access</div>
    </div>

    <nav class="py-2">
      <a class="link" href="dashboard.php">🏠 Dashboard</a>
      <a class="link" href="categories.php">➕ Add Categories</a>
      <a class="link" href="add_challenge.php">➕ Add Challenge</a>
      <a class="link" href="manage_users.php">👥 Manage Users</a>
      <a class="link" href="leaderboard.php">🏆 Leaderboard</a>
      <a class="link text-red-300" href="../index.php">🚪 Logout</a>
    </nav>
  </aside>

  <!-- Main -->
  <main class="flex-1 p-5 md:p-8">

    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-3xl font-extrabold text-green-400">Manage Users</h1>
        <p class="text-sm text-[rgba(201,247,228,.65)] mt-1">
          Add users, change roles, and reset passwords (hashed & secure).
        </p>
      </div>

      <div class="flex gap-3 items-center">
        <div class="chip px-3 py-2 rounded-lg text-xs text-[rgba(201,247,228,.7)]">
          Logged in as <span class="text-green-300 font-bold">Admin</span>
        </div>
      </div>
    </div>

    <!-- Alerts -->
    <?php if($success): ?>
      <div class="glass border border-[rgba(45,226,138,.35)] p-4 rounded-xl mb-5">
        <div class="text-green-300 font-bold">✅ Success</div>
        <div class="text-sm text-[rgba(201,247,228,.8)] mt-1"><?= htmlspecialchars($success) ?></div>
      </div>
    <?php endif; ?>

    <?php if($error): ?>
      <div class="glass border border-[rgba(255,91,91,.35)] p-4 rounded-xl mb-5">
        <div class="text-red-300 font-bold">⚠️ Error</div>
        <div class="text-sm text-[rgba(201,247,228,.8)] mt-1"><?= htmlspecialchars($error) ?></div>
      </div>
    <?php endif; ?>

    <!-- Grid: Add User + Users Table -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

      <!-- Add User Card -->
      <section class="glass rounded-2xl p-5 xl:col-span-4">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold text-green-300">➕ Add New User</h2>
          <span class="chip text-xs px-2 py-1 rounded-md text-[rgba(201,247,228,.7)]">Create</span>
        </div>

        <form method="POST" class="space-y-3">
          <input type="hidden" name="action" value="add_user" />

          <div>
            <label class="text-xs text-[rgba(201,247,228,.65)]">Username</label>
            <input type="text" name="username" required class="input w-full mt-1 rounded-lg px-3 py-2" placeholder="e.g. player01">
          </div>

          <div>
            <label class="text-xs text-[rgba(201,247,228,.65)]">Password</label>
            <div class="flex gap-2 mt-1">
              <input id="addPw" type="password" name="password" required class="input w-full rounded-lg px-3 py-2" placeholder="Set a strong password">
              <button type="button" class="btn px-3 py-2 rounded-lg text-sm" onclick="togglePw('addPw', this)">Show</button>
            </div>
            <div class="text-xs text-[rgba(201,247,228,.55)] mt-1">Passwords are stored hashed, not readable.</div>
          </div>

          <div>
            <label class="text-xs text-[rgba(201,247,228,.65)]">Role</label>
            <select name="role" required class="input w-full mt-1 rounded-lg px-3 py-2">
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <button class="btn-primary w-full rounded-xl px-4 py-2 font-extrabold">
            Add User
          </button>
        </form>
      </section>

      <!-- Users Table Card -->
      <section class="glass rounded-2xl p-5 xl:col-span-8">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-4">
          <div>
            <h2 class="text-lg font-bold text-green-300">👥 Users</h2>
            <p class="text-xs text-[rgba(201,247,228,.6)]">Click Edit to update user details or reset password.</p>
          </div>

          <div class="flex gap-3 items-center">
            <input id="searchBox" class="input rounded-xl px-3 py-2 w-full md:w-80"
                   placeholder="Search by username / role / id...">
          </div>
        </div>

        <div class="table-wrap rounded-xl overflow-hidden border border-[rgba(45,226,138,.18)]">
          <table id="usersTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Score</th>
                <th>Created</th>
                <th>Last Login</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($users as $u): ?>
              <?php
                $uid = (int)$u['id'];
                $isAdmin = (($u['role'] ?? '') === 'admin');
                $isSelf  = ($uid === $admin_id);
              ?>
              <tr class="user-row"
                  data-id="<?= $uid ?>"
                  data-username="<?= htmlspecialchars(strtolower($u['username'] ?? ''), ENT_QUOTES) ?>"
                  data-role="<?= htmlspecialchars(strtolower($u['role'] ?? ''), ENT_QUOTES) ?>">
                <td><?= $uid ?></td>
                <td class="font-bold"><?= htmlspecialchars($u['username'] ?? '') ?></td>
                <td>
                  <?php if($isAdmin): ?>
                    <span class="chip px-2 py-1 rounded-lg text-xs text-green-300">admin</span>
                  <?php else: ?>
                    <span class="chip px-2 py-1 rounded-lg text-xs text-[rgba(201,247,228,.75)]">user</span>
                  <?php endif; ?>
                </td>
                <td><?= (int)($u['score'] ?? 0) ?></td>
                <td class="text-xs text-[rgba(201,247,228,.7)]"><?= htmlspecialchars($u['created_at'] ?? '-') ?></td>
                <td class="text-xs text-[rgba(201,247,228,.7)]"><?= htmlspecialchars($u['last_login'] ?? '-') ?></td>
                <td class="flex gap-2 py-2">
                  <button
                    class="btn px-3 py-2 rounded-xl text-sm"
                    onclick="openEditModal(
                      <?= (int)$uid ?>,
                      '<?= htmlspecialchars($u['username'] ?? '', ENT_QUOTES) ?>',
                      '<?= htmlspecialchars($u['role'] ?? 'user', ENT_QUOTES) ?>',
                      <?= $isAdmin ? 'true' : 'false' ?>,
                      <?= $isSelf ? 'true' : 'false' ?>
                    )"
                  >✏️ Edit</button>

                  <?php if(!$isAdmin && !$isSelf): ?>
                    <form method="POST" onsubmit="return confirm('Delete this user permanently?');">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="delete_user_id" value="<?= $uid ?>">
                      <button class="btn-danger px-3 py-2 rounded-xl text-sm font-extrabold">🗑 Delete</button>
                    </form>
                  <?php else: ?>
                    <button class="btn px-3 py-2 rounded-xl text-sm opacity-50 cursor-not-allowed" disabled>🛡 Protected</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div id="noResults" class="hidden text-center text-sm text-[rgba(201,247,228,.65)] mt-4">
          No matching users.
        </div>
      </section>

    </div>
  </main>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="fixed inset-0 hidden items-center justify-center modal-backdrop z-50 p-4">
  <div class="glass w-full max-w-lg rounded-2xl border border-[rgba(45,226,138,.25)]">
    <div class="flex items-center justify-between p-5 border-b border-[rgba(45,226,138,.15)]">
      <div>
        <div class="text-xs text-[rgba(201,247,228,.65)] tracking-widest">EDIT USER</div>
        <div class="text-xl font-extrabold text-green-300">Update account</div>
      </div>
      <button class="btn px-3 py-2 rounded-xl" onclick="closeEditModal()">✖</button>
    </div>

    <form method="POST" class="p-5 space-y-4" id="editForm">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="edit_user_id" id="edit_user_id">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="text-xs text-[rgba(201,247,228,.65)]">Username</label>
          <input type="text" name="edit_username" id="edit_username" required class="input w-full mt-1 rounded-lg px-3 py-2">
        </div>

        <div>
          <label class="text-xs text-[rgba(201,247,228,.65)]">Role</label>
          <select name="edit_role" id="edit_role" required class="input w-full mt-1 rounded-lg px-3 py-2">
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
        </div>
      </div>

      <div>
        <label class="text-xs text-[rgba(201,247,228,.65)]">Reset Password (optional)</label>
        <div class="flex gap-2 mt-1">
          <input id="editPw" type="password" name="edit_password" class="input w-full rounded-lg px-3 py-2"
                 placeholder="Leave blank to keep current password">
          <button type="button" class="btn px-3 py-2 rounded-lg text-sm" onclick="togglePw('editPw', this)">Show</button>
        </div>
        <div class="text-xs text-[rgba(201,247,228,.55)] mt-1">
          You can’t view the existing password (stored hashed). This sets a new one.
        </div>
      </div>

      <div id="editWarn" class="hidden glass border border-[rgba(255,91,91,.35)] p-3 rounded-xl">
        <div class="text-red-300 font-bold text-sm">Protected account</div>
        <div class="text-xs text-[rgba(201,247,228,.75)] mt-1">
          This user is protected (admin or your own account). Some actions may be restricted.
        </div>
      </div>

      <div class="flex gap-3 justify-end pt-2">
        <button type="button" class="btn px-4 py-2 rounded-xl" onclick="closeEditModal()">Cancel</button>
        <button class="btn-primary px-5 py-2 rounded-xl font-extrabold">Save Changes</button>
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

  // Search filter
  const searchBox = document.getElementById('searchBox');
  const rows = Array.from(document.querySelectorAll('.user-row'));
  const noResults = document.getElementById('noResults');

  function applyFilter(){
    const q = (searchBox.value || '').trim().toLowerCase();
    let visible = 0;

    rows.forEach(r => {
      const id = r.dataset.id || '';
      const u  = r.dataset.username || '';
      const role = r.dataset.role || '';
      const match = !q || id.includes(q) || u.includes(q) || role.includes(q);
      r.style.display = match ? '' : 'none';
      if (match) visible++;
    });

    noResults.classList.toggle('hidden', visible !== 0);
  }
  if (searchBox) searchBox.addEventListener('input', applyFilter);

  // Modal
  const editModal = document.getElementById('editModal');
  const edit_user_id = document.getElementById('edit_user_id');
  const edit_username = document.getElementById('edit_username');
  const edit_role = document.getElementById('edit_role');
  const editWarn = document.getElementById('editWarn');

  function openEditModal(id, username, role, isAdmin, isSelf){
    edit_user_id.value = id;
    edit_username.value = username;
    edit_role.value = role;

    // warning for protected accounts
    editWarn.classList.toggle('hidden', !(isAdmin || isSelf));

    // (Optional) if you want to block changing role for admins/self in UI:
    // edit_role.disabled = (isAdmin || isSelf);

    // clear password field each open
    const editPw = document.getElementById('editPw');
    if (editPw) editPw.value = '';
    const btns = editModal.querySelectorAll('button');
    btns.forEach(b => { if (b.textContent === 'Hide') b.textContent = 'Show'; });

    editModal.classList.remove('hidden');
    editModal.classList.add('flex');
  }

  function closeEditModal(){
    editModal.classList.add('hidden');
    editModal.classList.remove('flex');
  }

  // Close modal on backdrop click
  editModal.addEventListener('click', (e) => {
    if (e.target === editModal) closeEditModal();
  });

  // ESC closes modal
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !editModal.classList.contains('hidden')) closeEditModal();
  });
</script>

</body>
</html>
