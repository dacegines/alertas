<?php
// Incluir el autoloader de Composer para PHPMailer
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Datos de conexión a la base de datos
$servername = "127.0.0.1";
$username = "root";
$password = "Abcd123*";
$dbname = "gestion_obligaciones";

// Crear conexión a la base de datos
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    error_log("Conexión fallida: " . $conn->connect_error, 3, "/var/www/html/alertas/error_log.log");
    die("Conexión fallida: " . $conn->connect_error);
}
echo "Conexión exitosa a la base de datos<br>";

// Función para determinar el color según el tipo de notificación y los días restantes
function obtenerColorNotificacion($tipo_notificacion, $dias_restantes) {
    if ($tipo_notificacion == 'primera_notificacion' && $dias_restantes == 30) {
        return '#90ee90'; // Verde claro
    } elseif ($tipo_notificacion == 'segunda_notificacion' && $dias_restantes == 15) {
        return '#ffff99'; // Amarillo claro
    } elseif ($tipo_notificacion == 'tercera_notificacion' && $dias_restantes == 5) {
        return '#ffcc99'; // Naranja claro
    } else {
        return '#ced4da'; // Gris claro por defecto
    }
}

// Función para enviar correos según el tipo de notificación y días restantes
function enviarRecordatoriosPorTipo($conn, $tipo_notificacion, $dias_restantes) {
    $sql = "
        SELECT 
            a.nombre, 
            a.evidencia, 
            a.periodicidad, 
            a.responsable, 
            a.fecha_limite_cumplimiento, 
            a.origen_obligacion, 
            a.clausula_condicionante_articulo, 
            a.email,
            b.nombre AS nombre_notificacion,
            b.tipo_notificacion,
            DATEDIFF(a.fecha_limite_cumplimiento, CURDATE()) AS dias_faltantes 
        FROM 
            requisitos a 
        INNER JOIN 
            notificaciones b 
        ON 
            a.id_notificaciones = b.id_notificacion 
        WHERE 
            b.tipo_notificacion = ? 
            AND DATEDIFF(a.fecha_limite_cumplimiento, CURDATE()) = ? 
            AND a.approved = 0
        ORDER BY 
            b.tipo_notificacion ASC;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $tipo_notificacion, $dias_restantes);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            try {
                // Configuración del servidor SMTP
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'daniel.cervantes.gines@gmail.com';
                $mail->Password = 'wdmtjiwcwwfblybr';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                // Remitente y destinatario
                $mail->setFrom('daniel.cervantes.gines@gmail.com', 'Recordatorio');
                $mail->addAddress($row['email']);

                $backgroundColor = obtenerColorNotificacion($tipo_notificacion, $dias_restantes);

                // Contenido del correo en HTML con el color de fondo en el header
                $mail->isHTML(true);
                $mail->Subject = 'Recordatorio de fecha límite para cumplimiento de obligación';
                $mail->Body = "
                <html>
                <head>
                    <style>
                        .container {
                            font-family: Arial, sans-serif;
                            max-width: 600px;
                            margin: auto;
                            padding: 20px;
                            background-color: #f8f9fa;
                            border-radius: 10px;
                            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                        }
                        .header {
                            background-color: {$backgroundColor};
                            padding: 15px;
                            text-align: center;
                            border-radius: 10px 10px 0 0;
                            font-weight: bold;
                            color: black;
                        }
                        .details-card {
                            padding: 20px;
                            margin-top: 20px;
                            background-color: #ffffff;
                            border-radius: 10px;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <p style='color: red; text-align: center;'>¡Faltan {$dias_restantes} días para cumplir esta obligación!</p>
                        <div class='header'>{$row['nombre']}</div>
                        <div class='details-card'>
                            <p><strong>📝 Obligación:</strong> {$row['evidencia']}</p>
                            <p><strong>👤 Responsable:</strong> {$row['responsable']}</p>
                            <p><strong>🗓 Fecha Límite:</strong> {$row['fecha_limite_cumplimiento']}</p>
                            <p><strong>📄 Origen:</strong> {$row['origen_obligacion']}</p>
                            <p><strong>📜 Cláusula:</strong> {$row['clausula_condicionante_articulo']}</p>
                        </div>
                    </div>
                </body>
                </html>";

                $mail->AltBody = "Hola {$row['responsable']}, tienes pendiente la obligación '{$row['evidencia']}'.";

                $mail->send();
                echo "Correo enviado a {$row['email']}<br>";
            } catch (Exception $e) {
                error_log("Error al enviar a {$row['email']}: {$mail->ErrorInfo}", 3, "/var/www/html/alertas/error_log.log");
                echo "Error al enviar correo: {$mail->ErrorInfo}<br>";
            }
        }
    } else {
        echo "No hay recordatorios para {$tipo_notificacion} con {$dias_restantes} días restantes.<br>";
    }
}

// Enviar los recordatorios para distintos tipos de notificaciones
enviarRecordatoriosPorTipo($conn, 'primera_notificacion', 30);
enviarRecordatoriosPorTipo($conn, 'segunda_notificacion', 15);
enviarRecordatoriosPorTipo($conn, 'tercera_notificacion', 5);

$conn->close();
?>