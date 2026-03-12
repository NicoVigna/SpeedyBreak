<?php
    session_start();

    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo "Errore: Devi essere loggato per ordinare.";
        exit;
    }

    $host = "localhost";
    $user = "root";
    $pass = "";
    $db = "my_saqlain";

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        die("Errore connessione");
    }

    $data = json_decode(file_get_contents("php://input"), true);

    $id_utente = intval($_SESSION['user_id']);
    if ($id_utente <= 0) {
        http_response_code(403);
        echo "Errore: Sessione non valida.";
        exit;
    }
    $data_ritiro = date("Y-m-d H:i:s", strtotime("+20 minutes"));

    $stmt_ordine = $conn->prepare("
        INSERT INTO SB_ordine (stato, metodo, id_utente, nota, data_ritiro)
        VALUES ('In attesa', 'Contanti', ?, '', ?)
    ");
    $stmt_ordine->bind_param("is", $id_utente, $data_ritiro);
    $stmt_ordine->execute();
    $id_ordine = $conn->insert_id;
    $stmt_ordine->close();

    $stmt_select = $conn->prepare("SELECT id_prodotto FROM SB_prodotto WHERE nome = ?");
    $stmt_insert = $conn->prepare("
        INSERT INTO SB_dettaglio_ordine (id_ordine, id_prodotto, quantita)
        VALUES (?, ?, ?)
    ");

    foreach ($data as $item) {
        $name = isset($item['name']) ? trim($item['name']) : '';
        $q = isset($item['quantity']) ? intval($item['quantity']) : 0;

        if ($name === '' || $q <= 0 || $q > 30) continue;

        $stmt_select->bind_param("s", $name);
        $stmt_select->execute();
        $res = $stmt_select->get_result();
        $row = $res->fetch_assoc();

        if (!$row) continue;

        $id_prodotto = $row['id_prodotto'];

        $stmt_insert->bind_param("iii", $id_ordine, $id_prodotto, $q);
        $stmt_insert->execute();
    }

    $stmt_select->close();
    $stmt_insert->close();

    echo "ok";
?>