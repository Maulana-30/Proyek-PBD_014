
<?php
session_start();

require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        try {
            $sql = "SELECT iduser, username, password, idrole
                    FROM `user`
                    WHERE username = :username
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'username' => $username
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $user &&
                password_verify($password, $user['password'])
            ) {
                // Buat session ID baru setelah login berhasil.
                session_regenerate_id(true);

                $_SESSION['iduser'] = (int) $user['iduser'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['idrole'] = (int) $user['idrole'];

                header('Location: dashboard.php');
                exit;
            }

            $error = 'Username atau password salah!';
        } catch (PDOException $e) {
            $error = 'Login gagal. Silakan coba kembali.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ATK STORE</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f2f6fc;
            color: #17315c;
        }

        .login-box {
            width: 100%;
            max-width: 380px;
            padding: 32px 26px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(20, 50, 90, 0.10);
        }

        .logo {
            width: 54px;
            height: 54px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 16px;
            border-radius: 14px;
            background: #e5efff;
            color: #1769e0;
            font-size: 25px;
            font-weight: bold;
        }

        h1 {
            margin: 0;
            text-align: center;
            font-size: 25px;
        }

        .subtitle {
            margin: 10px 0 25px;
            text-align: center;
            color: #72819a;
            font-size: 14px;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #d7e0ed;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus {
            outline: 2px solid #b9d4ff;
            border-color: #1769e0;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            border: none;
            border-radius: 8px;
            background: #1769e0;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1055bd;
        }

        .error {
            padding: 10px;
            border-radius: 8px;
            background: #fff0f0;
            color: #c62828;
            font-size: 13px;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <div class="logo">A</div>

        <h1>ATK STORE</h1>
        <p class="subtitle">Sistem Pengelolaan Barang</p>

        <?php if ($error !== ''): ?>
            <p class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="username">Username</label>
            <input
                type="text"
                id="username"
                name="username"
                placeholder="Masukkan username"
                maxlength="45"
                autocomplete="username"
                required
            >

            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Masukkan password"
                autocomplete="current-password"
                required
            >

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
