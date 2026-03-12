
<?php
    session_start();

    // controllo se l'utente ha una sessione attiva
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403); // Accesso negato
        echo "Errore: Devi essere loggato per ordinare.";
        exit;
    }

    $host="localhost";
    $user="root";
    $pass="";
    $db="my_saqlain";

    $conn = new mysqli($host,$user,$pass,$db);

    if($conn->connect_error){
        die("Errore connessione");
    }

    $data = json_decode(file_get_contents("php://input"),true);

    $id_utente = intval($_SESSION['user_id']);
    if ($id_utente <= 0) {
        http_response_code(403);
        echo "Errore: Sessione non valida.";
        exit;
    }

    $allowed_metodi = ['Contanti', 'Carta', 'Bancomat'];
    $metodo_raw = isset($data['metodo']) ? $data['metodo'] : "Contanti";
    $metodo = in_array($metodo_raw, $allowed_metodi) ? $metodo_raw : "Contanti";

    $nota = isset($data['nota']) ? substr(trim($data['nota']), 0, 500) : "";
    $data_ritiro = date("Y-m-d H:i:s", strtotime("+20 minutes"));

    $items = isset($data['items']) ? $data['items'] : $data;

    $stmt_ordine = $conn->prepare("
        INSERT INTO SB_ordine (stato, metodo, id_utente, nota, data_ritiro)
        VALUES ('In attesa', ?, ?, ?, ?)
    ");
    $stmt_ordine->bind_param("siss", $metodo, $id_utente, $nota, $data_ritiro);
    $stmt_ordine->execute();
    $id_ordine = $conn->insert_id;
    $stmt_ordine->close();

    $stmt_dettaglio = $conn->prepare("
        SELECT id_prodotto FROM SB_prodotto WHERE nome = ?
    ");
    $stmt_insert = $conn->prepare("
        INSERT INTO SB_dettaglio_ordine (id_ordine, id_prodotto, quantita)
        VALUES (?, ?, ?)
    ");

    foreach ($items as $item) {
        $nome = isset($item['name']) ? trim($item['name']) : '';
        $quantita = isset($item['quantity']) ? intval($item['quantity']) : 0;

        if ($quantita <= 0 || $quantita > 30 || $nome === '') continue;

        $stmt_dettaglio->bind_param("s", $nome);
        $stmt_dettaglio->execute();
        $res = $stmt_dettaglio->get_result();
        $row = $res->fetch_assoc();

        if (!$row) continue;

        $id_prodotto = $row['id_prodotto'];

        $stmt_insert->bind_param("iii", $id_ordine, $id_prodotto, $quantita);
        $stmt_insert->execute();
    }

    $stmt_dettaglio->close();
    $stmt_insert->close();

    echo "Ordine salvato";

?>