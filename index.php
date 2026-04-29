<?php

session_start();

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');     
define('DB_USER', 'root');
define('DB_PASS', 'root');    
define('DB_NAME', 'cropfit');

try {
   
    $dsnServer = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
    $bootstrap = new PDO($dsnServer, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $bootstrap->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

   
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die("DB connection failed: " . htmlspecialchars($e->getMessage())
        . "<br>Check DB_HOST / DB_PORT / DB_USER / DB_PASS at the top of index.php.");
}


function ensure_schema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user (
        userID INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('user','admin') DEFAULT 'user',
        status ENUM('active','inactive') DEFAULT 'active'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS crop (
        cropID INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        waterRequirement ENUM('Low','Moderate','High'),
        growthDuration INT,
        preferredSoil VARCHAR(50),
        preferredSeason VARCHAR(50),
        suitabilityScore DECIMAL(3,1)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS consultation (
        consultID INT AUTO_INCREMENT PRIMARY KEY,
        userID INT,
        region VARCHAR(100),
        soilType VARCHAR(50),
        season VARCHAR(50),
        cropID INT NULL,
        date DATE,
        FOREIGN KEY (userID) REFERENCES user(userID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS recommendation (
        recommendID INT AUTO_INCREMENT PRIMARY KEY,
        consultID INT,
        recommendedDate DATE,
        FOREIGN KEY (consultID) REFERENCES consultation(consultID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS recommendation_crops (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recommendID INT,
        cropID INT,
        FOREIGN KEY (recommendID) REFERENCES recommendation(recommendID) ON DELETE CASCADE,
        FOREIGN KEY (cropID) REFERENCES crop(cropID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS savedcrop (
        savedID INT AUTO_INCREMENT PRIMARY KEY,
        userID INT,
        cropID INT,
        progress INT DEFAULT 0,
        startDate DATE NULL,
        currentStageIndex INT NULL,
        savedAt DATE NULL,
        FOREIGN KEY (userID) REFERENCES user(userID) ON DELETE CASCADE,
        FOREIGN KEY (cropID) REFERENCES crop(cropID) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    
    $colExists = function($table, $col) use ($pdo) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                               WHERE TABLE_SCHEMA = DATABASE()
                                 AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $col]);
        return (int)$stmt->fetchColumn() > 0;
    };
    if (!$colExists('user', 'status'))                  $pdo->exec("ALTER TABLE user        ADD status ENUM('active','inactive') DEFAULT 'active'");
    if (!$colExists('consultation', 'cropID'))          $pdo->exec("ALTER TABLE consultation ADD cropID INT NULL");
    if (!$colExists('savedcrop', 'startDate'))          $pdo->exec("ALTER TABLE savedcrop   ADD startDate DATE NULL");
    if (!$colExists('savedcrop', 'currentStageIndex'))  $pdo->exec("ALTER TABLE savedcrop   ADD currentStageIndex INT NULL");
    if (!$colExists('savedcrop', 'savedAt'))            $pdo->exec("ALTER TABLE savedcrop   ADD savedAt DATE NULL");
}

function ensure_seed_data(PDO $pdo) {
    
    $hasAdmin = (int)$pdo->query("SELECT COUNT(*) FROM user WHERE role='admin'")->fetchColumn();
    if ($hasAdmin === 0) {
        $users = [
            ['Sara Ahmed',    'sara@example.com',    'pass123',  'user'],
            ['Michael Scott', 'michael@example.com', 'pass456',  'user'],
            ['Nora Khalid',   'noura@example.com',   'pass789',  'user'],
            ['System Admin',  'admin@system.com',    'admin123', 'admin'],
        ];
        $stmt = $pdo->prepare("INSERT IGNORE INTO user (name,email,password,role,status) VALUES (?,?,?,?, 'active')");
        foreach ($users as $u) {
            $stmt->execute([$u[0], $u[1], password_hash($u[2], PASSWORD_DEFAULT), $u[3]]);
        }
    }
    
    $cropCount = (int)$pdo->query("SELECT COUNT(*) FROM crop")->fetchColumn();
    if ($cropCount === 0) {
        $crops = [
            ['Wheat','Moderate',120,'Clay','Winter',8.5],
            ['Tomato','High',90,'Loamy','Spring',9.2],
            ['Date Palm','Low',365,'Sandy','All Seasons',9.8],
            ['Maize','Moderate',100,'Loamy','Summer',7.5],
            ['Barley','Low',110,'Sandy','Winter',8.0],
            ['Potato','Moderate',110,'Loamy','Autumn',8.7],
            ['Cucumber','High',60,'Silt','Spring',7.9],
            ['Carrot','Moderate',75,'Sandy','Autumn',8.2],
            ['Lettuce','High',45,'Loamy','Winter',9.0],
            ['Onion','Low',150,'Silt','Winter',8.4],
            ['Watermelon','Moderate',85,'Sandy','Summer',9.5],
            ['Bell Pepper','High',80,'Loamy','Spring',8.1],
            ['Grapes','Low',180,'Rocky','Spring',9.3],
            ['Olives','Low',365,'Rocky','All Seasons',9.7],
            ['Strawberry','High',120,'Loamy','Winter',8.8],
            ['Alfalfa','High',30,'Clay','Summer',7.2],
            ['Garlic','Low',240,'Silt','Autumn',8.9],
            ['Eggplant','Moderate',90,'Loamy','Summer',8.3],
            ['Spinach','Moderate',50,'Clay','Winter',7.8],
            ['Lemon','Moderate',365,'Sandy','All Seasons',9.1],
        ];
        $stmt = $pdo->prepare("INSERT INTO crop (name,waterRequirement,growthDuration,preferredSoil,preferredSeason,suitabilityScore) VALUES (?,?,?,?,?,?)");
        foreach ($crops as $c) $stmt->execute($c);
    }
}

try {
    ensure_schema($pdo);
    ensure_seed_data($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    die("Schema setup failed: " . htmlspecialchars($e->getMessage()));
}


function current_user_id() { return $_SESSION['user_id'] ?? null; }
function current_role()    { return $_SESSION['user_role'] ?? null; }
function require_login() {
    if (!current_user_id()) {
        http_response_code(401);
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }
}
function require_admin() {
    require_login();
    if (current_role() !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin only']);
        exit;
    }
}


function crop_icon($name) {
    $icons = [
        'Wheat' => '🌾', 'Tomato' => '🍅', 'Date Palm' => '🌴', 'Maize' => '🌽',
        'Barley' => '🌾', 'Potato' => '🥔', 'Cucumber' => '🥒', 'Carrot' => '🥕',
        'Lettuce' => '🥬', 'Onion' => '🧅', 'Watermelon' => '🍉', 'Bell Pepper' => '🫑',
        'Grapes' => '🍇', 'Olives' => '🫒', 'Strawberry' => '🍓', 'Alfalfa' => '🌱',
        'Garlic' => '🧄', 'Eggplant' => '🍆', 'Spinach' => '🥬', 'Lemon' => '🍋'
    ];
    return $icons[$name] ?? '🌱';
}


function crop_stages($name) {
    $custom = [
        'Tomato'      => ['Sowing','Germination','Vegetative','Flowering','Fruiting','Harvest'],
        'Lettuce'     => ['Sowing','Germination','Leaf Growth','Harvest'],
        'Carrot'      => ['Sowing','Germination','Vegetative','Root Development','Harvest'],
        'Bell Pepper' => ['Sowing','Germination','Vegetative','Flowering','Fruiting','Harvest'],
        'Cucumber'    => ['Sowing','Germination','Vine Growth','Flowering','Fruiting','Harvest'],
        'Spinach'     => ['Sowing','Germination','Leaf Growth','Harvest'],
    ];
    return $custom[$name] ?? ['Sowing','Germination','Vegetative','Flowering','Harvest'];
}


function enrich_crop($c) {
    $c['imageIcon']       = crop_icon($c['name']);
    $c['stages']          = crop_stages($c['name']);
    $c['waterReq']        = $c['waterRequirement'] ?? 'Moderate';
    $c['soilCompatibility'] = $c['preferredSoil'] ?? '';
    $c['season']          = $c['preferredSeason'] ?? '';
    $c['growthDurationDays'] = (int)$c['growthDuration'];
    $c['growthDuration']  = ((int)$c['growthDuration']) . ' days';
    $c['suitabilityScore']= round(((float)$c['suitabilityScore']) * 10);
    $c['sunlight']        = 'Full sun';
    $c['landArea']        = '0.3m²';
    return $c;
}


if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $api    = $_GET['api'];
    $method = $_SERVER['REQUEST_METHOD'];
    $body   = json_decode(file_get_contents('php://input'), true) ?: [];

    try {
      
        if ($api === 'session') {
            if (current_user_id()) {
                echo json_encode(['user' => [
                    'id'    => $_SESSION['user_id'],
                    'name'  => $_SESSION['user_name'],
                    'email' => $_SESSION['user_email'],
                    'role'  => $_SESSION['user_role'],
                ]]);
            } else {
                echo json_encode(['user' => null]);
            }
            exit;
        }

        if ($api === 'login' && $method === 'POST') {
            $email    = trim($body['email'] ?? '');
            $password = $body['password'] ?? '';
            if (!$email || !$password) { echo json_encode(['error' => 'Email and password required']); exit; }

            $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
            $stmt->execute([$email]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$u)                          { echo json_encode(['error' => 'Account not found']); exit; }
            if (!password_verify($password, $u['password'])) {
                                                echo json_encode(['error' => 'Invalid password']); exit; }
            if (($u['status'] ?? 'active') !== 'active') {
                                                echo json_encode(['error' => 'Account is deactivated']); exit; }

            $_SESSION['user_id']    = (int)$u['userID'];
            $_SESSION['user_name']  = $u['name'];
            $_SESSION['user_email'] = $u['email'];
            $_SESSION['user_role']  = $u['role'];
            echo json_encode(['success' => true, 'user' => [
                'id' => (int)$u['userID'], 'name' => $u['name'],
                'email' => $u['email'], 'role' => $u['role']
            ]]);
            exit;
        }

        if ($api === 'register' && $method === 'POST') {
            $name     = trim($body['name'] ?? '');
            $email    = trim($body['email'] ?? '');
            $password = $body['password'] ?? '';
            if (!$name || !$email || !$password) { echo json_encode(['error' => 'All fields are required']); exit; }
            if (strlen($password) < 4)           { echo json_encode(['error' => 'Password must be at least 4 characters']); exit; }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['error' => 'Invalid email']); exit; }

            $check = $pdo->prepare("SELECT userID FROM user WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) { echo json_encode(['error' => 'Email already registered']); exit; }

            $stmt = $pdo->prepare("INSERT INTO user (name,email,password,role,status) VALUES (?,?,?, 'user','active')");
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($api === 'logout') {
            session_destroy();
            echo json_encode(['success' => true]);
            exit;
        }

       
        if ($api === 'crops' && $method === 'GET') {
            require_login();
            $rows = $pdo->query("SELECT * FROM crop ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array_map('enrich_crop', $rows));
            exit;
        }

        if ($api === 'crops' && $method === 'POST') {
            require_admin();
            $name = trim($body['name'] ?? '');
            if (!$name) { echo json_encode(['error' => 'Crop name is required']); exit; }
            $check = $pdo->prepare("SELECT cropID FROM crop WHERE LOWER(name) = LOWER(?)");
            $check->execute([$name]);
            if ($check->fetch()) { echo json_encode(['error' => 'Crop already exists']); exit; }

            $stmt = $pdo->prepare("INSERT INTO crop (name,waterRequirement,growthDuration,preferredSoil,preferredSeason,suitabilityScore) VALUES (?,?,?,?,?,?)");
            $stmt->execute([
                $name,
                $body['waterRequirement'] ?? 'Moderate',
                (int)($body['growthDuration'] ?? 60),
                $body['preferredSoil']   ?? 'Loamy',
                $body['preferredSeason'] ?? 'Spring',
                (float)($body['suitabilityScore'] ?? 8.0),
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            exit;
        }

        if ($api === 'crops' && $method === 'PUT') {
            require_admin();
            $id   = (int)($_GET['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (!$id || !$name) { echo json_encode(['error' => 'Missing data']); exit; }
            $check = $pdo->prepare("SELECT cropID FROM crop WHERE LOWER(name) = LOWER(?) AND cropID <> ?");
            $check->execute([$name, $id]);
            if ($check->fetch()) { echo json_encode(['error' => 'Another crop already has that name']); exit; }

            $stmt = $pdo->prepare("UPDATE crop SET name=?, waterRequirement=?, growthDuration=?, preferredSoil=?, preferredSeason=?, suitabilityScore=? WHERE cropID=?");
            $stmt->execute([
                $name,
                $body['waterRequirement'] ?? 'Moderate',
                (int)($body['growthDuration'] ?? 60),
                $body['preferredSoil']   ?? 'Loamy',
                $body['preferredSeason'] ?? 'Spring',
                (float)($body['suitabilityScore'] ?? 8.0),
                $id,
            ]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($api === 'crops' && $method === 'DELETE') {
            require_admin();
            $id = (int)($_GET['id'] ?? 0);
            $pdo->prepare("DELETE FROM savedcrop    WHERE cropID = ?")->execute([$id]);
            $pdo->prepare("UPDATE consultation SET cropID = NULL WHERE cropID = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM crop WHERE cropID = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        }

       
        if ($api === 'recommendations' && $method === 'POST') {
            require_login();
            $region = trim($body['region']   ?? '');
            $soil   = trim($body['soilType'] ?? '');
            $season = trim($body['season']   ?? '');

            
            $stmt = $pdo->prepare(
                "SELECT *,
                        ((preferredSoil = ?) + (preferredSeason = ? OR preferredSeason = 'All Seasons')) AS matchScore
                 FROM crop
                 WHERE preferredSoil = ?
                    OR preferredSeason = ?
                    OR preferredSeason = 'All Seasons'
                 ORDER BY matchScore DESC, suitabilityScore DESC
                 LIMIT 8"
            );
            $stmt->execute([$soil, $season, $soil, $season]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

           
            if (count($rows) === 0) {
                $rows = $pdo->query("SELECT * FROM crop ORDER BY suitabilityScore DESC LIMIT 6")
                            ->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(array_map('enrich_crop', $rows));
            exit;
        }

       
        if ($api === 'consultations' && $method === 'GET') {
            require_login();
            
            $search = trim($_GET['search'] ?? '');
            if (current_role() === 'admin') {
                $sql = "SELECT c.*, u.name AS userName, u.email AS userEmail, cr.name AS cropName
                        FROM consultation c
                        LEFT JOIN user u ON c.userID = u.userID
                        LEFT JOIN crop cr ON c.cropID = cr.cropID";
                $rows = $pdo->query($sql . " ORDER BY c.consultID DESC")->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT c.*, cr.name AS cropName
                     FROM consultation c
                     LEFT JOIN crop cr ON c.cropID = cr.cropID
                     WHERE c.userID = ?
                     ORDER BY c.consultID DESC"
                );
                $stmt->execute([current_user_id()]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            foreach ($rows as &$c) {
                $c['consultId']  = 'CONS-' . str_pad($c['consultID'], 3, '0', STR_PAD_LEFT);
                $c['cropIcon']   = $c['cropName'] ? crop_icon($c['cropName']) : '';
                $c['createdAt']  = $c['date'];
            }
            if ($search !== '') {
                $needle = strtolower($search);
                $rows = array_values(array_filter($rows, function($c) use ($needle) {
                    return strpos(strtolower($c['consultId']), $needle) !== false
                        || strpos(strtolower($c['cropName'] ?? ''), $needle) !== false
                        || strpos(strtolower($c['userEmail'] ?? ''), $needle) !== false;
                }));
            }
            echo json_encode($rows);
            exit;
        }

        if ($api === 'consultations' && $method === 'POST') {
            require_login();
            $region = trim($body['region']   ?? '');
            $soil   = trim($body['soilType'] ?? '');
            $season = trim($body['season']   ?? '');
            $cropID = isset($body['cropID']) ? (int)$body['cropID'] : null;
            if (!$region || !$soil || !$season) { echo json_encode(['error' => 'Region, soil and season are required']); exit; }

            $stmt = $pdo->prepare("INSERT INTO consultation (userID,region,soilType,season,cropID,date) VALUES (?,?,?,?,?,?)");
            $stmt->execute([current_user_id(), $region, $soil, $season, $cropID, date('Y-m-d')]);
            $id = $pdo->lastInsertId();
            echo json_encode([
                'success'   => true,
                'consultID' => (int)$id,
                'consultId' => 'CONS-' . str_pad($id, 3, '0', STR_PAD_LEFT)
            ]);
            exit;
        }

        if ($api === 'consultations' && $method === 'PUT') {
            require_login();
            $id = (int)($_GET['id'] ?? 0);
            $check = $pdo->prepare("SELECT * FROM consultation WHERE consultID=? AND userID=?");
            $check->execute([$id, current_user_id()]);
            if (!$check->fetch()) { echo json_encode(['error' => 'Consultation not found']); exit; }

            $stmt = $pdo->prepare("UPDATE consultation SET region=?, soilType=?, season=? WHERE consultID=?");
            $stmt->execute([$body['region'] ?? '', $body['soilType'] ?? '', $body['season'] ?? '', $id]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($api === 'consultations' && $method === 'DELETE') {
            require_login();
            $id = (int)($_GET['id'] ?? 0);
           
            $stmt = $pdo->prepare("SELECT cropID FROM consultation WHERE consultID=? AND userID=?");
            $stmt->execute([$id, current_user_id()]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['error' => 'Not found']); exit; }
            $cropID = $row['cropID'];

            $pdo->prepare("DELETE FROM consultation WHERE consultID=? AND userID=?")
                ->execute([$id, current_user_id()]);

           
            if ($cropID) {
                $check = $pdo->prepare("SELECT 1 FROM consultation WHERE userID=? AND cropID=?");
                $check->execute([current_user_id(), $cropID]);
                if (!$check->fetch()) {
                    $pdo->prepare("DELETE FROM savedcrop WHERE userID=? AND cropID=?")
                        ->execute([current_user_id(), $cropID]);
                }
            }
            echo json_encode(['success' => true]);
            exit;
        }

        
        if ($api === 'saved-crops' && $method === 'GET') {
            require_login();
            $stmt = $pdo->prepare(
                "SELECT s.savedID, s.cropID, s.startDate, s.currentStageIndex, s.savedAt,
                        c.name, c.waterRequirement, c.growthDuration, c.preferredSoil,
                        c.preferredSeason, c.suitabilityScore
                 FROM savedcrop s
                 JOIN crop c ON s.cropID = c.cropID
                 WHERE s.userID = ?
                 ORDER BY s.savedID DESC"
            );
            $stmt->execute([current_user_id()]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out  = [];
            foreach ($rows as $r) {
                $crop = enrich_crop($r);
                $crop['savedID']           = (int)$r['savedID'];
                $crop['startDate']         = $r['startDate'];
                $crop['currentStageIndex'] = $r['currentStageIndex'] === null ? null : (int)$r['currentStageIndex'];
                $crop['savedAt']           = $r['savedAt'];
                $out[] = $crop;
            }
            echo json_encode($out);
            exit;
        }

        if ($api === 'saved-crops' && $method === 'POST') {
            require_login();
            $cropID = (int)($body['cropID'] ?? 0);
            if (!$cropID) { echo json_encode(['error' => 'cropID is required']); exit; }

            $check = $pdo->prepare("SELECT savedID FROM savedcrop WHERE userID=? AND cropID=?");
            $check->execute([current_user_id(), $cropID]);
            if ($check->fetch()) { echo json_encode(['error' => 'Crop is already in your My Crops']); exit; }

            $stmt = $pdo->prepare("INSERT INTO savedcrop (userID, cropID, progress, savedAt) VALUES (?,?,?,?)");
            $stmt->execute([current_user_id(), $cropID, 0, date('Y-m-d')]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($api === 'saved-crops' && $method === 'DELETE') {
            require_login();
            $id = (int)($_GET['id'] ?? 0);
            $pdo->prepare("DELETE FROM savedcrop WHERE savedID=? AND userID=?")
                ->execute([$id, current_user_id()]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($api === 'start-planting' && $method === 'POST') {
            require_login();
            $id = (int)($body['savedID'] ?? 0);
            $stmt = $pdo->prepare("UPDATE savedcrop SET startDate=?, currentStageIndex=0 WHERE savedID=? AND userID=?");
            $stmt->execute([date('Y-m-d'), $id, current_user_id()]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($api === 'next-stage' && $method === 'POST') {
            require_login();
            $id = (int)($body['savedID'] ?? 0);
            $stmt = $pdo->prepare("SELECT s.currentStageIndex, c.name FROM savedcrop s JOIN crop c ON s.cropID=c.cropID WHERE s.savedID=? AND s.userID=?");
            $stmt->execute([$id, current_user_id()]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['error' => 'Not found']); exit; }
            $stages   = crop_stages($row['name']);
            $next     = ($row['currentStageIndex'] === null) ? 0 : (int)$row['currentStageIndex'] + 1;
            if ($next > count($stages) - 1) $next = count($stages) - 1;
            $pdo->prepare("UPDATE savedcrop SET currentStageIndex=? WHERE savedID=? AND userID=?")
                ->execute([$next, $id, current_user_id()]);
            echo json_encode(['success' => true, 'currentStageIndex' => $next]);
            exit;
        }

        if ($api === 'users' && $method === 'GET') {
            require_admin();
            $rows = $pdo->query("SELECT userID, name, email, role, status FROM user ORDER BY userID")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($rows);
            exit;
        }

        if ($api === 'user-status' && $method === 'POST') {
            require_admin();
            $id     = (int)($body['userID'] ?? 0);
            $status = ($body['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

            $stmt = $pdo->prepare("SELECT role FROM user WHERE userID=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['role'] === 'admin' && $status === 'inactive') {
                echo json_encode(['error' => 'Cannot deactivate an admin account']); exit;
            }
            $pdo->prepare("UPDATE user SET status=? WHERE userID=?")->execute([$status, $id]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($api === 'users' && $method === 'DELETE') {
            require_admin();
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT role FROM user WHERE userID=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { echo json_encode(['error' => 'User not found']); exit; }
            if ($row['role'] === 'admin') { echo json_encode(['error' => 'Admin accounts cannot be deleted']); exit; }

            $pdo->prepare("DELETE FROM savedcrop    WHERE userID=?")->execute([$id]);
            $pdo->prepare("DELETE FROM consultation WHERE userID=?")->execute([$id]);
            $pdo->prepare("DELETE FROM user         WHERE userID=?")->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        }

        echo json_encode(['error' => 'Unknown endpoint']);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}


if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }


    
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CropFit — Smart Crop Recommendation System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; background: #f8faf6; color: #1a2e1f; line-height: 1.5; }
.container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
hr { border: 0; border-top: 1px solid #e2efdb; margin: 14px 0; }

/* navbar */
.navbar { background: #fff; box-shadow: 0 2px 12px rgba(0,32,0,0.06); position: sticky; top: 0; z-index: 100; }
.nav-inner { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; padding: 12px 0; }
.logo { display: flex; align-items: center; gap: 10px; font-size: 1.3rem; font-weight: 700; color: #2b5e2b; }
.nav-links { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
.nav-links a { text-decoration: none; font-weight: 500; color: #2c4b2c; cursor: pointer; padding: 6px 0; font-size: 0.9rem; }
.nav-links a:hover, .nav-links a.active { color: #1f8730; border-bottom: 2px solid #1f8730; }

/* buttons */
.btn-primary, .btn-secondary, .btn-outline, .btn-danger, .btn-success, .btn-warning {
  border-radius: 40px; font-weight: 600; cursor: pointer; font-size: 0.85rem; border: 1px solid transparent; padding: 8px 18px;
}
.btn-primary { background: #2b6e2b; color: #fff; }
.btn-primary:hover { background: #1d551d; }
.btn-secondary { background: #eef4ea; color: #2b6e2b; border-color: #cbe2c1; }
.btn-outline { background: transparent; color: #2b6e2b; border-color: #2b6e2b; }
.btn-outline:hover { background: #2b6e2b; color: #fff; }
.btn-danger { background: #fff0f0; color: #b33; border-color: #f0c0c0; }
.btn-success { background: #e0f0e0; color: #2c6e2c; border-color: #b8d9b8; }
.btn-warning { background: #fff6e0; color: #a06000; border-color: #f0d8a0; }
.btn-primary[disabled] { opacity: 0.6; cursor: not-allowed; }

/* cards */
.card { background: #fff; border-radius: 20px; padding: 22px; box-shadow: 0 2px 12px rgba(0,0,0,0.03); border: 1px solid #e2f0dc; margin-bottom: 20px; }
.card h3 { font-size: 1.2rem; margin-bottom: 14px; display: flex; align-items: center; gap: 8px; color: #1f4f1f; }
.card h4 { font-size: 1rem; margin-bottom: 10px; color: #1f4f1f; }
.badge { background: #eef4e9; border-radius: 60px; padding: 4px 10px; font-size: 0.7rem; font-weight: 600; }

/* forms */
.form-group { margin-bottom: 16px; }
label { font-weight: 500; display: block; margin-bottom: 6px; font-size: 0.9rem; }
input, select { width: 100%; padding: 10px 14px; border-radius: 24px; border: 1px solid #cfe2c6; font-family: inherit; font-size: 0.9rem; background: #fff; }
input:focus, select:focus { outline: none; border-color: #3b9e3b; }

/* auth */
.auth-container { max-width: 450px; margin: 60px auto; }
.auth-tabs { display: flex; margin-bottom: 24px; border-bottom: 2px solid #e2f0dc; }
.auth-tab { flex: 1; text-align: center; padding: 12px; cursor: pointer; font-weight: 600; color: #5a7c5a; }
.auth-tab.active { color: #2b6e2b; border-bottom: 3px solid #2b6e2b; margin-bottom: -2px; }
.error-message { color: #dc3545; font-size: 0.85rem; margin-top: 6px; min-height: 1em; }
.success-message { color: #2c7a2c; background: #e0f0e0; padding: 10px; border-radius: 16px; margin-bottom: 12px; font-size: 0.9rem; }
.message-info { background: #e9f4e4; padding: 12px; border-radius: 14px; margin: 10px 0; font-size: 0.9rem; }

/* lists */
.consult-card { background: #fefaf0; border-radius: 16px; padding: 14px; margin-bottom: 12px; border: 1px solid #e6e0c5; }
.crop-card { background: #fafef7; border-radius: 16px; padding: 14px; margin-bottom: 12px; border: 1px solid #d4e8ca; cursor: pointer; transition: 0.2s; }
.crop-card:hover { background: #f0faea; transform: translateY(-2px); }
.recommendation-item { background: #fafef7; border-left: 4px solid #3b9e3b; padding: 12px 16px; border-radius: 14px; margin-bottom: 12px; }
.crop-details-expanded { margin-top: 10px; padding: 12px; background: #eef4ea; border-radius: 12px; display: none; font-size: 0.88rem; }
.crop-details-expanded.show { display: block; }
.flex-between { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
.consult-id-badge { background: #2b6e2b; color: #fff; padding: 3px 10px; border-radius: 30px; font-family: monospace; font-size: 0.75rem; }
.search-box { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.search-box input { flex: 1; min-width: 150px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin: 18px 0; flex-wrap: wrap; gap: 10px; }

/* growth stages */
.stages-container { display: flex; justify-content: space-between; margin: 20px 0; position: relative; flex-wrap: wrap; gap: 6px; }
.stage-item { text-align: center; flex: 1; min-width: 60px; position: relative; z-index: 2; }
.stage-icon { width: 44px; height: 44px; background: #eef4ea; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 5px; font-size: 1.3rem; }
.stage-item.active .stage-icon { background: #2b6e2b; color: #fff; box-shadow: 0 2px 8px rgba(43,110,43,0.3); }
.stage-item.completed .stage-icon { background: #4c9e4c; color: #fff; }
.stage-item.future .stage-icon { background: #e0e8dc; color: #8ba888; }
.stage-label { font-size: 0.7rem; font-weight: 500; }
.progress-line { position: absolute; top: 22px; left: 6%; right: 6%; height: 3px; background: #e0ecdb; z-index: 1; }
.progress-fill-line { position: absolute; top: 22px; left: 6%; height: 3px; background: #2b6e2b; z-index: 1; transition: width 0.3s; }

/* compare */
.crop-checkbox-group { display: flex; flex-wrap: wrap; gap: 10px; margin: 12px 0; }
.crop-checkbox { display: flex; align-items: center; gap: 6px; padding: 8px 14px; background: #f4faf0; border-radius: 40px; cursor: pointer; font-size: 0.85rem; user-select: none; }
.compare-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 0.88rem; }
.compare-table th, .compare-table td { border: 1px solid #e2efdb; padding: 8px 10px; text-align: left; }
.compare-table th { background: #f4faf0; }

/* admin tables */
.data-table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.data-table th, .data-table td { padding: 8px 10px; border-bottom: 1px solid #e2efdb; text-align: left; }
.data-table th { background: #f4faf0; color: #1f4f1f; }
.table-wrap { overflow-x: auto; }

/* modal */
.modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 20px; }
.modal.show { display: flex; }
.modal-content { background: #fff; border-radius: 20px; padding: 24px; max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.close-modal { background: none; border: 0; font-size: 1.6rem; cursor: pointer; color: #999; line-height: 1; }
</style>
</head>
<body>

<div id="app">
  <div class="container" style="padding: 60px 20px; text-align: center;">
    <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #2b6e2b;"></i>
    <p style="margin-top: 12px;">Loading…</p>
  </div>
</div>

<script>


const API = (endpoint, method = 'GET', data = null) => {
  const opts = { method, headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin' };
  if (data) opts.body = JSON.stringify(data);
  return fetch('?api=' + endpoint, opts).then(async r => {
    const txt = await r.text();
    let j; try { j = JSON.parse(txt); } catch { throw new Error('Invalid response: ' + txt); }
    if (!r.ok && j.error) throw new Error(j.error);
    return j;
  });
};

let currentUser = null;     
let allCrops    = [];       

async function boot() {
  try {
    const s = await API('session');
    currentUser = s.user;
  } catch (e) { currentUser = null; }
  if (!currentUser) renderAuth(); else renderMainApp();
}

function renderAuth() {
  document.getElementById('app').innerHTML = `
    <div class="auth-container">
      <div class="card" style="text-align:center;">
        <img src="logo.png" alt="CropFit" style="width:120px; height:120px; margin-bottom:10px; border-radius:50%; object-fit:cover;">
        <h2 style="color:#2b6e2b; margin-bottom:18px;">CropFit</h2>
        <div class="auth-tabs">
          <div class="auth-tab active" data-tab="login">Login</div>
          <div class="auth-tab" data-tab="register">Register</div>
        </div>
        <div id="auth-form-container" style="text-align:left;"></div>
      </div>
    </div>`;
  showLoginForm();
  document.querySelectorAll('.auth-tab').forEach(t => {
    t.addEventListener('click', () => {
      document.querySelectorAll('.auth-tab').forEach(x => x.classList.remove('active'));
      t.classList.add('active');
      t.dataset.tab === 'login' ? showLoginForm() : showRegisterForm();
    });
  });
}

function showLoginForm() {
  document.getElementById('auth-form-container').innerHTML = `
    <form id="loginForm">
      <div class="form-group"><label>Email</label><input type="email" id="loginEmail" placeholder="you@example.com"></div>
      <div class="form-group"><label>Password</label><input type="password" id="loginPassword" placeholder="••••••••"></div>
      <div id="loginError" class="error-message"></div>
      <button type="submit" class="btn-primary" style="width:100%; margin-top:6px;">Login</button>
    </form>`;
  document.getElementById('loginForm').addEventListener('submit', handleLogin);
}

function showRegisterForm() {
  document.getElementById('auth-form-container').innerHTML = `
    <form id="registerForm">
      <div class="form-group"><label>Full Name</label><input type="text" id="regName"></div>
      <div class="form-group"><label>Email</label><input type="email" id="regEmail"></div>
      <div class="form-group"><label>Password</label><input type="password" id="regPassword" placeholder="At least 4 characters"></div>
      <div class="form-group"><label>Confirm Password</label><input type="password" id="regConfirm"></div>
      <div id="registerError" class="error-message"></div>
      <button type="submit" class="btn-primary" style="width:100%; margin-top:6px;">Register</button>
    </form>`;
  document.getElementById('registerForm').addEventListener('submit', handleRegister);
}

async function handleLogin(e) {
  e.preventDefault();
  const email    = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value;
  const errEl    = document.getElementById('loginError');
  errEl.textContent = '';
  try {
    const r = await API('login', 'POST', { email, password });
    if (r.success) { currentUser = r.user; renderMainApp(); }
    else errEl.textContent = r.error || 'Login failed';
  } catch (err) { errEl.textContent = err.message; }
}

async function handleRegister(e) {
  e.preventDefault();
  const name     = document.getElementById('regName').value.trim();
  const email    = document.getElementById('regEmail').value.trim();
  const password = document.getElementById('regPassword').value;
  const confirm  = document.getElementById('regConfirm').value;
  const errEl    = document.getElementById('registerError');
  errEl.textContent = '';
  if (!name || !email || !password) { errEl.textContent = 'All fields are required'; return; }
  if (password.length < 4)          { errEl.textContent = 'Password must be at least 4 characters'; return; }
  if (password !== confirm)         { errEl.textContent = 'Passwords do not match'; return; }
  try {
    const r = await API('register', 'POST', { name, email, password });
    if (r.success) {
      document.getElementById('auth-form-container').innerHTML =
        `<div class="success-message">Registration successful! You can now log in.</div>`;
      setTimeout(() => { document.querySelector('.auth-tab[data-tab="login"]').click(); }, 1200);
    } else errEl.textContent = r.error || 'Registration failed';
  } catch (err) { errEl.textContent = err.message; }
}

async function renderMainApp() {
  if (currentUser.role === 'admin') renderAdminShell();
  else renderUserShell();

  try { allCrops = await API('crops'); } catch (e) { allCrops = []; }

  if (currentUser.role === 'admin') renderAdminPage('crops');
  else renderUserPage('addConsult');
}

async function logout() {
  await API('logout');
  currentUser = null;
  renderAuth();
}

function renderUserShell() {
  document.getElementById('app').innerHTML = `
    <div class="navbar"><div class="container nav-inner">
      <div class="logo">🌾 <span>CropFit</span></div>
      <div class="nav-links">
        <a data-page="addConsult"  class="active"><i class="fas fa-plus-circle"></i> Add Consultation</a>
        <a data-page="myConsults"><i class="fas fa-history"></i> My Consultations</a>
        <a data-page="myCrops"><i class="fas fa-seedling"></i> My Crops</a>
        <span class="badge"><i class="fas fa-user"></i> ${escapeHtml(currentUser.name)}</span>
        <button class="btn-outline" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</button>
      </div>
    </div></div>
    <main class="container" id="mainContent" style="margin-top:18px;"></main>`;
  document.getElementById('logoutBtn').addEventListener('click', logout);
  document.querySelectorAll('[data-page]').forEach(a => a.addEventListener('click', e => {
    e.preventDefault(); renderUserPage(a.dataset.page);
  }));
}

function renderUserPage(page) {
  document.querySelectorAll('[data-page]').forEach(a => a.classList.toggle('active', a.dataset.page === page));
  if (page === 'addConsult') return renderAddConsult();
  if (page === 'myConsults') return renderMyConsults();
  if (page === 'myCrops')    return renderMyCrops();
}

function renderAddConsult() {
  document.getElementById('mainContent').innerHTML = `
    <div class="card">
      <h3><i class="fas fa-plus-circle"></i> New Crop Consultation</h3>
      <p>Select your farming conditions to get personalized crop recommendations.</p>
      <hr>
      <div class="form-group"><label>📍 Region</label>
        <select id="regionSelect"><option value="">Select Region</option>
          <option>Central Region</option><option>Coastal Region</option>
          <option>Northern Region</option><option>Southern Region</option>
          <option>Eastern Region</option><option>Western Region</option>
        </select>
      </div>
      <div class="form-group"><label>🪴 Soil Type</label>
        <select id="soilTypeSelect"><option value="">Select Soil Type</option>
          <option>Loamy</option><option>Sandy</option><option>Clay</option>
          <option>Silt</option><option>Rocky</option>
        </select>
      </div>
      <div class="form-group"><label>🌱 Planting Season</label>
        <select id="seasonSelect"><option value="">Select Season</option>
          <option>Spring</option><option>Summer</option><option>Autumn</option><option>Winter</option>
        </select>
      </div>
      <button id="viewRecBtn" class="btn-primary" style="width:100%;">
        <i class="fas fa-search"></i> View Recommended Crops
      </button>
    </div>`;
  document.getElementById('viewRecBtn').addEventListener('click', async () => {
    const region   = document.getElementById('regionSelect').value;
    const soilType = document.getElementById('soilTypeSelect').value;
    const season   = document.getElementById('seasonSelect').value;
    if (!region || !soilType || !season) return alert('Please select region, soil type and season.');
    await showRecommendations(region, soilType, season);
  });
}

async function showRecommendations(region, soilType, season) {
  let recs = [];
  try { recs = await API('recommendations', 'POST', { region, soilType, season }); }
  catch (err) { return alert(err.message); }

  const main = document.getElementById('mainContent');
  main.innerHTML = `
    <div class="page-header">
      <button id="backBtn" class="btn-outline"><i class="fas fa-arrow-left"></i> Back</button>
      <h3>Recommended Crops · ${escapeHtml(region)} · ${escapeHtml(soilType)} · ${escapeHtml(season)}</h3>
      <span></span>
    </div>

    <div class="card">
      <h4><i class="fas fa-chart-simple"></i> Compare Recommended Crops</h4>
      <p style="font-size:0.85rem;">Select 2 or more crops to compare them side by side.</p>
      <div class="crop-checkbox-group">
        ${recs.map(c => `<label class="crop-checkbox">
          <input type="checkbox" class="compare-cb" value="${c.cropID}"> ${c.imageIcon} ${escapeHtml(c.name)}
        </label>`).join('')}
      </div>
      <button id="compareBtn" class="btn-primary"><i class="fas fa-chart-line"></i> Compare Selected</button>
      <div id="compareResult" style="margin-top:12px;"></div>
    </div>

    <div class="card">
      <h4><i class="fas fa-list-ul"></i> Recommended Crops</h4>
      <div id="recList">
        ${recs.map(c => recommendationItemHTML(c)).join('')}
      </div>
    </div>`;

  document.getElementById('backBtn').addEventListener('click', () => renderUserPage('addConsult'));

  document.getElementById('compareBtn').addEventListener('click', () => {
    const ids = Array.from(document.querySelectorAll('.compare-cb:checked')).map(c => parseInt(c.value));
    if (ids.length < 2) return alert('Please select at least 2 crops to compare.');
    const chosen = recs.filter(c => ids.includes(c.cropID));
    document.getElementById('compareResult').innerHTML = `
      <table class="compare-table">
        <thead><tr><th>Crop</th><th>Growth</th><th>Water</th><th>Soil</th><th>Season</th><th>Score</th></tr></thead>
        <tbody>
          ${chosen.map(c => `<tr>
            <td><strong>${c.imageIcon} ${escapeHtml(c.name)}</strong></td>
            <td>${escapeHtml(c.growthDuration)}</td>
            <td>${escapeHtml(c.waterReq)}</td>
            <td>${escapeHtml(c.soilCompatibility)}</td>
            <td>${escapeHtml(c.season)}</td>
            <td><strong style="color:#2c7a2c;">${c.suitabilityScore}%</strong></td>
          </tr>`).join('')}
        </tbody>
      </table>`;
  });

  document.querySelectorAll('.view-details-btn').forEach(btn => btn.addEventListener('click', () => {
    const id = btn.dataset.id;
    const box = document.getElementById('details-' + id);
    box.classList.toggle('show');
    btn.textContent = box.classList.contains('show') ? 'Hide Details' : 'View Details';
  }));

  document.querySelectorAll('.save-crop-btn').forEach(btn => btn.addEventListener('click', async () => {
    const cropID   = parseInt(btn.dataset.id);
    const cropName = btn.dataset.name;
    btn.disabled = true;
    try {

        const r1 = await API('saved-crops', 'POST', { cropID });
      if (!r1.success) { alert(r1.error || 'Could not save'); btn.disabled = false; return; }

        const r2 = await API('consultations', 'POST', { region, soilType, season, cropID });
      alert(`✅ ${cropName} saved to My Crops!\nConsultation ID: ${r2.consultId}`);
    } catch (err) { alert(err.message); btn.disabled = false; }
  }));
}

function recommendationItemHTML(c) {
  return `
    <div class="recommendation-item">
      <div class="flex-between">
        <div>
          <strong>${c.imageIcon} ${escapeHtml(c.name)}</strong><br>
          <small>Growth: ${escapeHtml(c.growthDuration)} · Water: ${escapeHtml(c.waterReq)}</small>
        </div>
        <div>
          <span style="color:#2c7a2c; font-weight:bold;">Suitability: ${c.suitabilityScore}%</span>
          <button class="btn-secondary view-details-btn" data-id="${c.cropID}" style="margin-left:8px;">View Details</button>
          <button class="btn-success save-crop-btn" data-id="${c.cropID}" data-name="${escapeAttr(c.name)}" style="margin-left:8px;">Save to My Crops</button>
        </div>
      </div>
      <div id="details-${c.cropID}" class="crop-details-expanded">
        <hr>
        <p><i class="fas fa-tint"></i> <strong>Water:</strong> ${escapeHtml(c.waterReq)}</p>
        <p><i class="fas fa-mountain"></i> <strong>Soil:</strong> ${escapeHtml(c.soilCompatibility)}</p>
        <p><i class="fas fa-sun"></i> <strong>Sunlight:</strong> ${escapeHtml(c.sunlight)}</p>
        <p><i class="fas fa-ruler"></i> <strong>Land Area:</strong> ${escapeHtml(c.landArea)}</p>
        <p><i class="fas fa-chart-line"></i> <strong>Growth Duration:</strong> ${escapeHtml(c.growthDuration)}</p>
        <p><i class="fas fa-calendar"></i> <strong>Best Season:</strong> ${escapeHtml(c.season)}</p>
      </div>
    </div>`;
}


    async function renderMyConsults(searchQuery = '') {
  let list = [];
  try {
    const url = 'consultations' + (searchQuery ? '&search=' + encodeURIComponent(searchQuery) : '');
    list = await API(url);
  } catch (e) { alert(e.message); }

  document.getElementById('mainContent').innerHTML = `
    <div class="card">
      <h3><i class="fas fa-folder-open"></i> My Consultations</h3>
      <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search by Consult ID (e.g. CONS-001)" value="${escapeAttr(searchQuery)}">
        <button id="searchBtn" class="btn-primary"><i class="fas fa-search"></i> Search</button>
        <button id="resetBtn" class="btn-outline">Reset</button>
      </div>
      <div id="consultList">${renderConsultListHTML(list)}</div>
    </div>`;

  document.getElementById('searchBtn').addEventListener('click', () => {
    const q = document.getElementById('searchInput').value.trim();
    if (!q) return alert('Please enter a search term.');
    renderMyConsults(q);
  });
  document.getElementById('resetBtn').addEventListener('click', () => renderMyConsults());
  attachConsultListEvents();
}

function renderConsultListHTML(list) {
  if (!list.length) return `<p class="message-info">No consultations yet. Use Add Consultation to create one.</p>`;
  return list.map(c => `
    <div class="consult-card">
      <div class="flex-between">
        <span class="consult-id-badge"><i class="fas fa-id-card"></i> ${c.consultId}</span>
        <span class="badge">${escapeHtml(c.createdAt || '')}</span>
      </div>
      <p style="margin-top:6px;">📍 <strong>${escapeHtml(c.region || '')}</strong> · 🪴 ${escapeHtml(c.soilType || '')} · 🌱 ${escapeHtml(c.season || '')}</p>
      <p>🌾 <strong>Crop:</strong> ${c.cropName ? c.cropIcon + ' ' + escapeHtml(c.cropName) : '<em>(none chosen)</em>'}</p>
      <div class="flex-between" style="margin-top:8px;">
        <button class="btn-secondary edit-consult" data-id="${c.consultID}"><i class="fas fa-edit"></i> Edit</button>
        <button class="btn-danger delete-consult" data-id="${c.consultID}"><i class="fas fa-trash"></i> Delete</button>
      </div>
    </div>`).join('');
}

function attachConsultListEvents() {
  document.querySelectorAll('.delete-consult').forEach(btn => btn.addEventListener('click', async () => {
    if (!confirm('Delete this consultation?')) return;
    try { await API('consultations&id=' + btn.dataset.id, 'DELETE'); renderMyConsults(); }
    catch (e) { alert(e.message); }
  }));
  document.querySelectorAll('.edit-consult').forEach(btn => btn.addEventListener('click', () =>
    showEditConsultModal(parseInt(btn.dataset.id))
  ));
}

async function showEditConsultModal(consultID) {
  const list = await API('consultations');
  const c = list.find(x => parseInt(x.consultID) === consultID);
  if (!c) return alert('Consultation not found');

  showModal(`
    <div class="modal-header">
      <h3><i class="fas fa-edit"></i> Edit Consultation ${c.consultId}</h3>
      <button class="close-modal" onclick="hideModal()">&times;</button>
    </div>
    <form id="editConsultForm">
      <div class="form-group"><label>📍 Region</label>
        <select id="eRegion">
          ${['Central Region','Coastal Region','Northern Region','Southern Region','Eastern Region','Western Region']
            .map(r => `<option ${r === c.region ? 'selected' : ''}>${r}</option>`).join('')}
        </select>
      </div>
      <div class="form-group"><label>🪴 Soil Type</label>
        <select id="eSoil">
          ${['Loamy','Sandy','Clay','Silt','Rocky'].map(s => `<option ${s === c.soilType ? 'selected' : ''}>${s}</option>`).join('')}
        </select>
      </div>
      <div class="form-group"><label>🌱 Season</label>
        <select id="eSeason">
          ${['Spring','Summer','Autumn','Winter'].map(s => `<option ${s === c.season ? 'selected' : ''}>${s}</option>`).join('')}
        </select>
      </div>
      <div class="form-group"><label>🌾 Crop (cannot be changed)</label>
        <input type="text" disabled value="${c.cropName ? c.cropIcon + ' ' + escapeAttr(c.cropName) : ''}">
      </div>
      <div class="flex-between" style="margin-top:14px;">
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        <button type="button" class="btn-outline" onclick="hideModal()">Cancel</button>
      </div>
    </form>`);

  document.getElementById('editConsultForm').addEventListener('submit', async e => {
    e.preventDefault();
    try {
      await API('consultations&id=' + consultID, 'PUT', {
        region:   document.getElementById('eRegion').value,
        soilType: document.getElementById('eSoil').value,
        season:   document.getElementById('eSeason').value
      });
      hideModal();
      renderMyConsults();
    } catch (err) { alert(err.message); }
  });
}


    async function renderMyCrops() {
  let saved = [];
  try { saved = await API('saved-crops'); } catch (e) { alert(e.message); }
  document.getElementById('mainContent').innerHTML = `
    <div class="card">
      <h3><i class="fas fa-seedling"></i> My Crops</h3>
      <p>Click a crop to view and update its growth progress.</p>
      <div id="savedList">${renderSavedListHTML(saved)}</div>
    </div>`;

  document.querySelectorAll('.saved-crop-card').forEach(card => card.addEventListener('click', e => {
    if (e.target.classList.contains('delete-saved')) return;
    showCropGrowth(parseInt(card.dataset.savedId));
  }));
  document.querySelectorAll('.delete-saved').forEach(btn => btn.addEventListener('click', async (e) => {
    e.stopPropagation();
    if (!confirm('Remove this crop from My Crops?')) return;
    try { await API('saved-crops&id=' + btn.dataset.savedId, 'DELETE'); renderMyCrops(); }
    catch (err) { alert(err.message); }
  }));
}

function renderSavedListHTML(list) {
  if (!list.length) return `<p class="message-info">No crops saved yet. Save crops from the recommendations page first.</p>`;
  return list.map(c => `
    <div class="crop-card saved-crop-card" data-saved-id="${c.savedID}">
      <div class="flex-between">
        <strong style="font-size:1.1rem;">${c.imageIcon} ${escapeHtml(c.name)}</strong>
        <span class="badge">${c.startDate ? '🌱 Growing' : '📌 Saved'}</span>
      </div>
      <p style="margin-top:6px;"><strong>Growth:</strong> ${escapeHtml(c.growthDuration)} · <strong>Water:</strong> ${escapeHtml(c.waterReq)} · <strong>Soil:</strong> ${escapeHtml(c.soilCompatibility)}</p>
      <div style="margin-top:8px; text-align:right;">
        <button class="btn-danger delete-saved" data-saved-id="${c.savedID}"><i class="fas fa-trash"></i> Remove</button>
      </div>
    </div>`).join('');
}

async function showCropGrowth(savedID) {
  const list = await API('saved-crops');
  const c    = list.find(x => x.savedID === savedID);
  if (!c) return renderMyCrops();
  const stages   = c.stages;
  const started  = !!c.startDate;
  const stageIdx = c.currentStageIndex == null ? 0 : c.currentStageIndex;
  const pct      = started ? ((stageIdx + 1) / stages.length) * 100 : 0;

  const stageIcons = {
    'Sowing':'🌱','Germination':'🌿','Vegetative':'🌳','Leaf Growth':'🍃',
    'Root Development':'🥔','Vine Growth':'🍇','Flowering':'🌸','Fruiting':'🍎','Harvest':'✂️'
  };

  document.getElementById('mainContent').innerHTML = `
    <div class="page-header">
      <button id="backBtn" class="btn-outline"><i class="fas fa-arrow-left"></i> Back to My Crops</button>
      <h3>${c.imageIcon} ${escapeHtml(c.name)} · Growth Progress</h3>
      <span></span>
    </div>
    <div class="card">
      ${!started ? `
        <div class="message-info">
          <p>You haven't started planting <strong>${escapeHtml(c.name)}</strong> yet. Click below to begin tracking growth stages.</p>
          <button class="btn-primary" id="startBtn" style="margin-top:10px;"><i class="fas fa-play-circle"></i> Start Planting</button>
        </div>` : `
        <div class="stages-container">
          <div class="progress-line"></div>
          <div class="progress-fill-line" style="width: calc(${pct}% - 12%);"></div>
          ${stages.map((s, i) => {
            const cls = i < stageIdx ? 'completed' : (i === stageIdx ? 'active' : 'future');
            return `<div class="stage-item ${cls}">
              <div class="stage-icon">${stageIcons[s] || '🌾'}</div>
              <div class="stage-label">${s}</div>
            </div>`;
          }).join('')}
        </div>
        <div class="flex-between" style="margin-top:14px;">
          <span><i class="fas fa-calendar-week"></i> Started: ${c.startDate}</span>
          ${stageIdx < stages.length - 1
            ? `<button class="btn-primary" id="nextStageBtn"><i class="fas fa-forward"></i> Next Stage</button>`
            : `<span class="badge" style="background:#2b6e2b; color:#fff; padding:6px 12px;"><i class="fas fa-check-circle"></i> Ready for Harvest!</span>`}
        </div>`}
    </div>`;
  document.getElementById('backBtn').addEventListener('click', renderMyCrops);
  document.getElementById('startBtn')?.addEventListener('click', async () => {
    try { await API('start-planting', 'POST', { savedID }); showCropGrowth(savedID); }
    catch (e) { alert(e.message); }
  });
  document.getElementById('nextStageBtn')?.addEventListener('click', async () => {
    try { await API('next-stage', 'POST', { savedID }); showCropGrowth(savedID); }
    catch (e) { alert(e.message); }
  });
}


function renderAdminShell() {
  document.getElementById('app').innerHTML = `
    <div class="navbar"><div class="container nav-inner">
      <div class="logo">🌾 <span>CropFit Admin</span></div>
      <div class="nav-links">
        <a data-admin="crops"    class="active"><i class="fas fa-seedling"></i> Manage Crops</a>
        <a data-admin="users"><i class="fas fa-users"></i> Manage Users</a>
        <a data-admin="consults"><i class="fas fa-chart-line"></i> User Consultations</a>
        <span class="badge"><i class="fas fa-user-shield"></i> ${escapeHtml(currentUser.name)}</span>
        <button class="btn-outline" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> Logout</button>
      </div>
    </div></div>
    <main class="container" id="mainContent" style="margin-top:18px;"></main>`;
  document.getElementById('logoutBtn').addEventListener('click', logout);
  document.querySelectorAll('[data-admin]').forEach(a => a.addEventListener('click', e => {
    e.preventDefault(); renderAdminPage(a.dataset.admin);
  }));
}

function renderAdminPage(page) {
  document.querySelectorAll('[data-admin]').forEach(a => a.classList.toggle('active', a.dataset.admin === page));
  if (page === 'crops')    return renderAdminCrops();
  if (page === 'users')    return renderAdminUsers();
  if (page === 'consults') return renderAdminConsults();
}

async function renderAdminCrops() {
  let crops = [];
  try { crops = await API('crops'); allCrops = crops; } catch (e) { alert(e.message); }
  document.getElementById('mainContent').innerHTML = `
    <div class="card">
      <h3><i class="fas fa-seedling"></i> Manage Crops</h3>
      <button class="btn-primary" id="addCropBtn"><i class="fas fa-plus"></i> Add New Crop</button>
      <div class="table-wrap" style="margin-top:14px;">
        <table class="data-table">
          <thead><tr><th></th><th>Name</th><th>Water</th><th>Growth</th><th>Soil</th><th>Season</th><th>Score</th><th>Actions</th></tr></thead>
          <tbody>
            ${crops.map(c => `<tr>
              <td style="font-size:1.3rem;">${c.imageIcon}</td>
              <td><strong>${escapeHtml(c.name)}</strong></td>
              <td>${escapeHtml(c.waterReq)}</td>
              <td>${escapeHtml(c.growthDuration)}</td>
              <td>${escapeHtml(c.soilCompatibility)}</td>
              <td>${escapeHtml(c.season)}</td>
              <td>${c.suitabilityScore}%</td>
              <td>
                <button class="btn-secondary edit-crop" data-id="${c.cropID}">Edit</button>
                <button class="btn-danger delete-crop" data-id="${c.cropID}">Delete</button>
              </td>
            </tr>`).join('')}
          </tbody>
        </table>
      </div>
    </div>`;
  document.getElementById('addCropBtn').addEventListener('click', () => showCropModal(null));
  document.querySelectorAll('.edit-crop').forEach(b => b.addEventListener('click', () =>
    showCropModal(crops.find(c => c.cropID == b.dataset.id))));
  document.querySelectorAll('.delete-crop').forEach(b => b.addEventListener('click', async () => {
    if (!confirm('Delete this crop?')) return;
    try { await API('crops&id=' + b.dataset.id, 'DELETE'); renderAdminCrops(); }
    catch (e) { alert(e.message); }
  }));
}

function showCropModal(crop) {
  const isEdit = !!crop;
  showModal(`
    <div class="modal-header">
      <h3><i class="fas fa-${isEdit ? 'edit' : 'plus'}"></i> ${isEdit ? 'Edit Crop' : 'Add New Crop'}</h3>
      <button class="close-modal" onclick="hideModal()">&times;</button>
    </div>
    <form id="cropForm">
      <div class="form-group"><label>Name</label><input id="cName" value="${isEdit ? escapeAttr(crop.name) : ''}" required></div>
      <div class="form-group"><label>Water Requirement</label>
        <select id="cWater">
          ${['Low','Moderate','High'].map(o => `<option ${isEdit && crop.waterReq === o ? 'selected' : ''}>${o}</option>`).join('')}
        </select>
      </div>
      <div class="form-group"><label>Growth Duration (days)</label>
        <input type="number" id="cGrowth" value="${isEdit ? crop.growthDurationDays : 60}" min="1" required>
      </div>
      <div class="form-group"><label>Preferred Soil</label><input id="cSoil" value="${isEdit ? escapeAttr(crop.soilCompatibility) : 'Loamy'}"></div>
      <div class="form-group"><label>Preferred Season</label>
        <select id="cSeason">
          ${['Spring','Summer','Autumn','Winter','All Seasons'].map(o => `<option ${isEdit && crop.season === o ? 'selected' : ''}>${o}</option>`).join('')}
        </select>
      </div>
      <div class="form-group"><label>Suitability Score (1–10)</label>
        <input type="number" id="cScore" step="0.1" min="1" max="10" value="${isEdit ? (crop.suitabilityScore / 10).toFixed(1) : '8.0'}" required>
      </div>
      <div class="flex-between" style="margin-top:14px;">
        <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save</button>
        <button type="button" class="btn-outline" onclick="hideModal()">Cancel</button>
      </div>
    </form>`);
  document.getElementById('cropForm').addEventListener('submit', async e => {
    e.preventDefault();
    const payload = {
      name: document.getElementById('cName').value.trim(),
      waterRequirement: document.getElementById('cWater').value,
      growthDuration: parseInt(document.getElementById('cGrowth').value),
      preferredSoil: document.getElementById('cSoil').value.trim(),
      preferredSeason: document.getElementById('cSeason').value,
      suitabilityScore: parseFloat(document.getElementById('cScore').value)
    };
    try {
      if (isEdit) await API('crops&id=' + crop.cropID, 'PUT', payload);
      else        await API('crops', 'POST', payload);
      hideModal();
      renderAdminCrops();
    } catch (err) { alert(err.message); }
  });
}

async function renderAdminUsers() {
  let users = [];
  try { users = await API('users'); } catch (e) { alert(e.message); }
  document.getElementById('mainContent').innerHTML = `
    <div class="card">
      <h3><i class="fas fa-users"></i> Manage Users</h3>
      <p class="message-info">Admin accounts cannot be deactivated or deleted.</p>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            ${users.map(u => `<tr>
              <td>${escapeHtml(u.name)}</td>
              <td>${escapeHtml(u.email)}</td>
              <td>${escapeHtml(u.role)}</td>
              <td>${u.status === 'active'
                ? '<span class="badge" style="background:#e0f0e0; color:#2c7a2c;">Active</span>'
                : '<span class="badge" style="background:#ffe0e0; color:#b33;">Inactive</span>'}</td>
              <td>
                ${u.role !== 'admin'
                  ? (u.status === 'active'
                      ? `<button class="btn-warning toggle-user" data-id="${u.userID}" data-status="inactive">Deactivate</button>`
                      : `<button class="btn-success toggle-user" data-id="${u.userID}" data-status="active">Activate</button>`)
                  : ''}
                ${u.role !== 'admin' ? `<button class="btn-danger delete-user" data-id="${u.userID}">Delete</button>` : ''}
              </td>
            </tr>`).join('')}
          </tbody>
        </table>
      </div>
    </div>`;
  document.querySelectorAll('.toggle-user').forEach(b => b.addEventListener('click', async () => {
    try { await API('user-status', 'POST', { userID: parseInt(b.dataset.id), status: b.dataset.status }); renderAdminUsers(); }
    catch (e) { alert(e.message); }
  }));
  document.querySelectorAll('.delete-user').forEach(b => b.addEventListener('click', async () => {
    if (!confirm('Delete this user account? This cannot be undone.')) return;
    try { await API('users&id=' + b.dataset.id, 'DELETE'); renderAdminUsers(); }
    catch (e) { alert(e.message); }
  }));
}

async function renderAdminConsults(searchQuery = '') {
  let list = [];
  try {
    const url = 'consultations' + (searchQuery ? '&search=' + encodeURIComponent(searchQuery) : '');
    list = await API(url);
  } catch (e) { alert(e.message); }
  document.getElementById('mainContent').innerHTML = `
    <div class="card">
      <h3><i class="fas fa-chart-line"></i> All User Consultations</h3>
      <div class="search-box">
        <input id="searchInput" placeholder="Search by Consult ID, crop, or user email" value="${escapeAttr(searchQuery)}">
        <button id="searchBtn" class="btn-primary">Search</button>
        <button id="resetBtn" class="btn-outline">Reset</button>
      </div>
      <div>
        ${list.length === 0 ? '<p class="message-info">No consultations found.</p>' : list.map(c => `
          <div class="consult-card">
            <div class="flex-between">
              <span class="consult-id-badge">${c.consultId}</span>
              <span class="badge">${escapeHtml(c.createdAt || '')}</span>
            </div>
            <p style="margin-top:6px;">👤 <strong>${escapeHtml(c.userName || c.userEmail || 'Unknown')}</strong> (${escapeHtml(c.userEmail || '')})</p>
            <p>📍 ${escapeHtml(c.region || '')} · 🪴 ${escapeHtml(c.soilType || '')} · 🌱 ${escapeHtml(c.season || '')}</p>
            <p>🌾 Crop: ${c.cropName ? c.cropIcon + ' ' + escapeHtml(c.cropName) : '<em>(none)</em>'}</p>
          </div>`).join('')}
      </div>
    </div>`;
  document.getElementById('searchBtn').addEventListener('click', () => {
    const q = document.getElementById('searchInput').value.trim();
    renderAdminConsults(q);
  });
  document.getElementById('resetBtn').addEventListener('click', () => renderAdminConsults());
}


function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function escapeAttr(s) { return escapeHtml(s); }

function showModal(html) {
  let m = document.getElementById('modalRoot');
  if (!m) {
    m = document.createElement('div');
    m.id = 'modalRoot'; m.className = 'modal';
    document.body.appendChild(m);
  }
  m.innerHTML = `<div class="modal-content">${html}</div>`;
  m.classList.add('show');
}
function hideModal() {
  const m = document.getElementById('modalRoot');
  if (m) m.classList.remove('show');
}
window.hideModal = hideModal;

boot();
</script>
</body>
</html>
