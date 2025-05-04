<?php
header('Content-Type: application/json');

// Función para importar el CSV a la base de datos
function importCSV($csvFile, $db) {
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
       // Leer la fila de encabezado
       $header = fgetcsv($handle, 1000, ",");
       if(!$header) {
           throw new Exception("CSV sin encabezado.");
       }
       // Encontrar índices para "Gatillo" y "Pregunta"
       $gatilloIndex = array_search("Gatillo", $header);
       $preguntaIndex = array_search("Pregunta", $header);
       if($gatilloIndex === false || $preguntaIndex === false){
          throw new Exception("El CSV debe tener las columnas 'Gatillo' y 'Pregunta'.");
       }
       // Se esperan columnas de Respuesta1 a Respuesta13 (se ignoran columnas extra)
       while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
          if(trim($row[$preguntaIndex]) == '') continue; // omitir filas sin pregunta
          $gatillo = trim($row[$gatilloIndex]);
          $question = trim($row[$preguntaIndex]);
          // Preparar arreglo para respuestas 1 a 13
          $answers = [];
          for($i = 1; $i <= 13; $i++){
             $colIndex = $i + 1; // Asumiendo que la columna 0 es Gatillo y 1 es Pregunta
             $answers[$i] = (isset($row[$colIndex]) ? trim($row[$colIndex]) : '');
          }
          // Insertar en la tabla
          $stmt = $db->prepare("INSERT INTO faq (gatillo, question, respuesta1, respuesta2, respuesta3, respuesta4, respuesta5, respuesta6, respuesta7, respuesta8, respuesta9, respuesta10, respuesta11, respuesta12, respuesta13) VALUES (:gatillo, :question, :respuesta1, :respuesta2, :respuesta3, :respuesta4, :respuesta5, :respuesta6, :respuesta7, :respuesta8, :respuesta9, :respuesta10, :respuesta11, :respuesta12, :respuesta13)");
          $stmt->execute([
            ':gatillo' => $gatillo,
            ':question' => $question,
            ':respuesta1' => $answers[1],
            ':respuesta2' => $answers[2],
            ':respuesta3' => $answers[3],
            ':respuesta4' => $answers[4],
            ':respuesta5' => $answers[5],
            ':respuesta6' => $answers[6],
            ':respuesta7' => $answers[7],
            ':respuesta8' => $answers[8],
            ':respuesta9' => $answers[9],
            ':respuesta10' => $answers[10],
            ':respuesta11' => $answers[11],
            ':respuesta12' => $answers[12],
            ':respuesta13' => $answers[13]
          ]);
       }
       fclose($handle);
    } else {
      throw new Exception("No se pudo abrir el archivo CSV.");
    }
}

try {
    $dbFile = 'chatbot.db';
    $initDb = !file_exists($dbFile);
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Si es la primera ejecución, crear la tabla e importar el CSV
    if ($initDb) {
      $createTableQuery = "CREATE TABLE IF NOT EXISTS faq (
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
      )";
      $db->exec($createTableQuery);

      // Intentar importar desde el CSV (por ejemplo, faq.csv)
      if (file_exists('faq.csv')) {
          try {
             importCSV('faq.csv', $db);
          } catch (Exception $e) {
             // Si falla la importación, insertar un nodo por defecto
             $stmt = $db->prepare("INSERT INTO faq (gatillo, question, respuesta1) VALUES (:gatillo, :question, :respuesta1)");
             $stmt->execute([':gatillo' => '¡Hola! ¿En qué te puedo ayudar?', ':question' => 'Seleccione una opción:', ':respuesta1' => 'Opción de ejemplo']);
          }
      } else {
          // Si no se encuentra el CSV, insertar un nodo por defecto
          $stmt = $db->prepare("INSERT INTO faq (gatillo, question, respuesta1) VALUES (:gatillo, :question, :respuesta1)");
          $stmt->execute([':gatillo' => '¡Hola! ¿En qué te puedo ayudar?', ':question' => 'Seleccione una opción:', ':respuesta1' => 'Opción de ejemplo']);
      }
    }

    // Si se hace una petición GET, se busca el nodo correspondiente
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      if (isset($_GET['trigger']) && trim($_GET['trigger']) != '') {
          $trigger = trim($_GET['trigger']);
          $stmt = $db->prepare("SELECT * FROM faq WHERE gatillo = :trigger LIMIT 1");
          $stmt->execute([':trigger' => $trigger]);
      } else {
          // Sin trigger, se devuelve el primer nodo (por ejemplo, el de id más bajo)
          $stmt = $db->query("SELECT * FROM faq ORDER BY id ASC LIMIT 1");
      }
      $node = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($node) {
         // Construir el arreglo de respuestas a partir de respuesta1 a respuesta13
         $answers = [];
         for ($i = 1; $i <= 13; $i++) {
             $key = 'respuesta' . $i;
             if (isset($node[$key]) && trim($node[$key]) != '') {
                $answers[] = $node[$key];
             }
         }
         echo json_encode([
            'question' => $node['question'],
            'answers' => $answers
         ]);
      } else {
         echo json_encode([
            'question' => 'Fin de la conversación. Gracias por usar el chatbot.',
            'answers' => []
         ]);
      }
    } else {
         echo json_encode(['error' => 'Método no permitido.']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
