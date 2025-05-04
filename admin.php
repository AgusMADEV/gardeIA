<?php
session_start();

// Conexión a la base de datos SQLite y creación de tablas si no existen
try {
    $db = new PDO('sqlite:chatbot.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Crear tabla de conversaciones (faq) si no existe
    $db->exec("CREATE TABLE IF NOT EXISTS faq (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        gatillo TEXT NOT NULL,
        question TEXT NOT NULL,
        respuesta1 TEXT,
        respuesta2 TEXT,
        respuesta3 TEXT,
        respuesta4 TEXT,
        respuesta5 TEXT,
        respuesta6 TEXT,
        respuesta7 TEXT,
        respuesta8 TEXT,
        respuesta9 TEXT,
        respuesta10 TEXT,
        respuesta11 TEXT,
        respuesta12 TEXT,
        respuesta13 TEXT
    )");

    // Crear tabla de usuarios si no existe
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL,
        username TEXT NOT NULL,
        password TEXT NOT NULL
    )");

    // Insertar usuario inicial si no existe
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->execute([':username' => 'jocarsa']);
    if ($stmt->fetchColumn() == 0) {
        $db->prepare("INSERT INTO users (name, email, username, password) VALUES (:name, :email, :username, :password)")
           ->execute([
                ':name'     => 'Jose Vicente Carratala',
                ':email'    => 'info@josevicentecarratala.com',
                ':username' => 'jocarsa',
                ':password' => 'jocarsa' // Nota: En producción, usa un hash para la contraseña.
           ]);
    }
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Procesar logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Si el usuario no está logueado, mostrar el formulario de login
if (!isset($_SESSION['admin_logged_in'])) {
    $login_error = '';
    if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND password = :password LIMIT 1");
        $stmt->execute([':username' => $username, ':password' => $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $user;
            header("Location: admin.php");
            exit;
        } else {
            $login_error = "Invalid credentials.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>Admin Login - jocarsa | violet</title>
      <link rel="stylesheet" href="admin.css">
    </head>
    <body>
      <div class="login-container">
         <h1>jocarsa | violet Admin Login</h1>
         <?php if ($login_error) echo "<p class='error'>$login_error</p>"; ?>
         <form method="post" action="admin.php">
            <input type="hidden" name="login" value="1">
            <label>Username:</label>
            <input type="text" name="username" required>
            <label>Password:</label>
            <input type="password" name="password" required>
            <input type="submit" value="Login">
         </form>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Procesamiento de acciones CRUD
$message = '';
if ($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action == 'add_conversation') {
        // Agregar nuevo nodo de conversación
        $parent_id = isset($_POST['parent_id']) ? $_POST['parent_id'] : 'none';
        $response_slot = isset($_POST['response_slot']) ? $_POST['response_slot'] : '';
        $gatillo = trim($_POST['gatillo']);
        $question_text = trim($_POST['question_text']);
        $respuestas = [];
        for ($i = 1; $i <= 13; $i++) {
            $field = 'respuesta' . $i;
            $respuestas[$i] = isset($_POST[$field]) ? trim($_POST[$field]) : '';
        }
        if ($gatillo == '' || $question_text == '') {
            $message = "Gatillo and Question are required.";
        } else {
            $stmt = $db->prepare("INSERT INTO faq (gatillo, question, respuesta1, respuesta2, respuesta3, respuesta4, respuesta5, respuesta6, respuesta7, respuesta8, respuesta9, respuesta10, respuesta11, respuesta12, respuesta13)
                VALUES (:gatillo, :question, :respuesta1, :respuesta2, :respuesta3, :respuesta4, :respuesta5, :respuesta6, :respuesta7, :respuesta8, :respuesta9, :respuesta10, :respuesta11, :respuesta12, :respuesta13)");
            $stmt->execute([
                ':gatillo' => $gatillo,
                ':question' => $question_text,
                ':respuesta1' => $respuestas[1],
                ':respuesta2' => $respuestas[2],
                ':respuesta3' => $respuestas[3],
                ':respuesta4' => $respuestas[4],
                ':respuesta5' => $respuestas[5],
                ':respuesta6' => $respuestas[6],
                ':respuesta7' => $respuestas[7],
                ':respuesta8' => $respuestas[8],
                ':respuesta9' => $respuestas[9],
                ':respuesta10'=> $respuestas[10],
                ':respuesta11'=> $respuestas[11],
                ':respuesta12'=> $respuestas[12],
                ':respuesta13'=> $respuestas[13]
            ]);
            $new_id = $db->lastInsertId();
            // Si se seleccionó un nodo padre y un slot, actualizar el registro del padre
            if ($parent_id !== 'none' && $response_slot != '') {
                $column = 'respuesta' . intval($response_slot);
                $stmt = $db->prepare("UPDATE faq SET $column = :trigger WHERE id = :parent_id");
                $stmt->execute([
                    ':trigger' => $gatillo,
                    ':parent_id' => $parent_id
                ]);
            }
            $message = "New conversation node added with ID $new_id.";
        }
    } elseif ($action == 'delete_conversation' && isset($_POST['id'])) {
        $id = $_POST['id'];
        $stmt = $db->prepare("DELETE FROM faq WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $message = "Conversation node with ID $id deleted.";
    } elseif ($action == 'edit_conversation' && isset($_POST['id'])) {
        $id = $_POST['id'];
        $gatillo = trim($_POST['gatillo']);
        $question_text = trim($_POST['question_text']);
        $respuestas = [];
        for ($i = 1; $i <= 13; $i++) {
            $field = 'respuesta' . $i;
            $respuestas[$i] = isset($_POST[$field]) ? trim($_POST[$field]) : '';
        }
        if ($gatillo == '' || $question_text == '') {
            $message = "Gatillo and Question are required for editing.";
        } else {
            $stmt = $db->prepare("UPDATE faq SET 
                    gatillo = :gatillo, 
                    question = :question, 
                    respuesta1 = :respuesta1, 
                    respuesta2 = :respuesta2, 
                    respuesta3 = :respuesta3, 
                    respuesta4 = :respuesta4, 
                    respuesta5 = :respuesta5, 
                    respuesta6 = :respuesta6, 
                    respuesta7 = :respuesta7, 
                    respuesta8 = :respuesta8, 
                    respuesta9 = :respuesta9, 
                    respuesta10 = :respuesta10, 
                    respuesta11 = :respuesta11, 
                    respuesta12 = :respuesta12, 
                    respuesta13 = :respuesta13 
                    WHERE id = :id");
            $stmt->execute([
                ':gatillo' => $gatillo,
                ':question' => $question_text,
                ':respuesta1' => $respuestas[1],
                ':respuesta2' => $respuestas[2],
                ':respuesta3' => $respuestas[3],
                ':respuesta4' => $respuestas[4],
                ':respuesta5' => $respuestas[5],
                ':respuesta6' => $respuestas[6],
                ':respuesta7' => $respuestas[7],
                ':respuesta8' => $respuestas[8],
                ':respuesta9' => $respuestas[9],
                ':respuesta10' => $respuestas[10],
                ':respuesta11' => $respuestas[11],
                ':respuesta12' => $respuestas[12],
                ':respuesta13' => $respuestas[13],
                ':id' => $id
            ]);
            $message = "Conversation node with ID $id updated.";
        }
    } elseif ($action == 'delete_user' && isset($_POST['id'])) {
        $id = $_POST['id'];
        // Evitar eliminar el usuario admin inicial
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id AND username != 'jocarsa'");
        $stmt->execute([':id' => $id]);
        $message = "User deleted.";
    } elseif ($action == 'edit_user' && isset($_POST['id'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        if ($name == '' || $email == '' || $username == '' || $password == '') {
            $message = "All user fields are required.";
        } else {
            $stmt = $db->prepare("UPDATE users SET name = :name, email = :email, username = :username, password = :password WHERE id = :id");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':username' => $username,
                ':password' => $password,
                ':id' => $id
            ]);
            $message = "User updated.";
        }
    } elseif ($action == 'add_user') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        if ($name == '' || $email == '' || $username == '' || $password == '') {
            $message = "All user fields are required.";
        } else {
            $stmt = $db->prepare("INSERT INTO users (name, email, username, password) VALUES (:name, :email, :username, :password)");
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':username' => $username,
                ':password' => $password
            ]);
            $message = "New user added.";
        }
    }
}

// Determinar qué contenido mostrar según la navegación
$page = isset($_GET['page']) ? $_GET['page'] : 'conversations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Panel - jocarsa | violet</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
  <div id="admin-panel">
    <header>
      <h1>jocarsa | violet</h1>
      <nav class="top-nav">
        <a href="admin.php?action=logout">Logout</a>
      </nav>
    </header>
    <div id="admin-body">
      <aside class="sidebar">
        <ul>
          <li><a href="admin.php?page=conversations">Conversations</a></li>
          <li><a href="admin.php?page=users">Users</a></li>
          <li><a href="admin.php?page=new">New Conversation</a></li>
        </ul>
      </aside>
      <section class="content-area">
        <?php if ($message): ?>
          <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($page == 'conversations'): ?>
          <h2>Conversations</h2>
          <table>
            <tr>
              <th>ID</th>
              <th>Gatillo</th>
              <th>Question</th>
              <th>Actions</th>
            </tr>
            <?php
              $stmt = $db->query("SELECT * FROM faq ORDER BY id ASC");
              while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  echo "<tr>";
                  echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['gatillo']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['question']) . "</td>";
                  echo "<td>";
                  echo "<form method='post' style='display:inline;'>
                          <input type='hidden' name='id' value='" . $row['id'] . "'>
                          <input type='hidden' name='action' value='delete_conversation'>
                          <input type='submit' value='Delete' onclick='return confirm(\"Are you sure?\")'>
                        </form> ";
                  echo "<a href='admin.php?page=edit_conversation&id=" . $row['id'] . "'>Edit</a>";
                  echo "</td>";
                  echo "</tr>";
              }
            ?>
          </table>
        <?php elseif ($page == 'edit_conversation' && isset($_GET['id'])): 
                $edit_id = intval($_GET['id']);
                $stmt = $db->prepare("SELECT * FROM faq WHERE id = :id");
                $stmt->execute([':id' => $edit_id]);
                $edit_node = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($edit_node):
        ?>
          <h2>Edit Conversation Node (ID <?php echo $edit_node['id']; ?>)</h2>
          <form method="post">
            <input type="hidden" name="action" value="edit_conversation">
            <input type="hidden" name="id" value="<?php echo $edit_node['id']; ?>">
            <label>Trigger (Gatillo):</label>
            <input type="text" name="gatillo" value="<?php echo htmlspecialchars($edit_node['gatillo']); ?>" required>
            <label>Question:</label>
            <textarea name="question_text" required><?php echo htmlspecialchars($edit_node['question']); ?></textarea>
            <?php for ($i = 1; $i <= 13; $i++): ?>
              <label>Respuesta <?php echo $i; ?>:</label>
              <input type="text" name="respuesta<?php echo $i; ?>" value="<?php echo htmlspecialchars($edit_node['respuesta' . $i]); ?>">
            <?php endfor; ?>
            <input type="submit" value="Update Conversation">
          </form>
        <?php endif; elseif ($page == 'users'): ?>
          <h2>Users</h2>
          <table>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Username</th>
              <th>Actions</th>
            </tr>
            <?php
              $stmt = $db->query("SELECT * FROM users ORDER BY id ASC");
              while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  echo "<tr>";
                  echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                  echo "<td>";
                  echo "<form method='post' style='display:inline;'>
                          <input type='hidden' name='id' value='" . $row['id'] . "'>
                          <input type='hidden' name='action' value='delete_user'>
                          <input type='submit' value='Delete' onclick='return confirm(\"Are you sure?\")'>
                        </form> ";
                  echo "<a href='admin.php?page=edit_user&id=" . $row['id'] . "'>Edit</a>";
                  echo "</td>";
                  echo "</tr>";
              }
            ?>
          </table>
          <h3>Add New User</h3>
          <form method="post">
            <input type="hidden" name="action" value="add_user">
            <label>Name:</label>
            <input type="text" name="name" required>
            <label>Email:</label>
            <input type="text" name="email" required>
            <label>Username:</label>
            <input type="text" name="username" required>
            <label>Password:</label>
            <input type="text" name="password" required>
            <input type="submit" value="Add User">
          </form>
        <?php elseif ($page == 'edit_user' && isset($_GET['id'])): 
                $edit_user_id = intval($_GET['id']);
                $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->execute([':id' => $edit_user_id]);
                $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($edit_user):
        ?>
          <h2>Edit User (ID <?php echo $edit_user['id']; ?>)</h2>
          <form method="post">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">
            <label>Name:</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($edit_user['name']); ?>" required>
            <label>Email:</label>
            <input type="text" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required>
            <label>Username:</label>
            <input type="text" name="username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required>
            <label>Password:</label>
            <input type="text" name="password" value="<?php echo htmlspecialchars($edit_user['password']); ?>" required>
            <input type="submit" value="Update User">
          </form>
        <?php endif; // Added missing endif for the edit_user block ?>
        <?php elseif ($page == 'new'): ?>
          <h2>Add New Conversation Node</h2>
          <form method="post">
            <input type="hidden" name="action" value="add_conversation">
            <label>Parent Conversation (optional):</label>
            <select name="parent_id" id="parent_id">
              <option value="none">None</option>
              <?php
                $stmt = $db->query("SELECT id, question FROM faq ORDER BY id ASC");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  echo "<option value='" . $row['id'] . "'>" . htmlspecialchars("ID " . $row['id'] . " - " . $row['question']) . "</option>";
                }
              ?>
            </select>
            <label>If selecting a parent, choose response slot to update:</label>
            <select name="response_slot" id="response_slot">
              <option value="">-- Select Slot (1-13) --</option>
              <?php for ($i = 1; $i <= 13; $i++): ?>
                 <option value="<?php echo $i; ?>">Respuesta <?php echo $i; ?></option>
              <?php endfor; ?>
            </select>
            <label>Trigger (Gatillo):</label>
            <input type="text" name="gatillo" required>
            <label>Question:</label>
            <textarea name="question_text" required></textarea>
            <?php for ($i = 1; $i <= 13; $i++): ?>
              <label>Respuesta <?php echo $i; ?>:</label>
              <input type="text" name="respuesta<?php echo $i; ?>">
            <?php endfor; ?>
            <input type="submit" value="Add Conversation">
          </form>
        <?php endif; ?>
      </section>
    </div>
  </div>
</body>
</html>
