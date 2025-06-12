<?php
// Script para instalar PHPMailer usando Composer
// Ejecutar este script desde la línea de comandos: php install_phpmailer.php

echo "Instalando PHPMailer...\n";

// Verificar si Composer está instalado
$composer_exists = shell_exec('composer --version');
if (!$composer_exists) {
    echo "Error: Composer no está instalado. Por favor, instala Composer primero.\n";
    echo "Visita https://getcomposer.org/download/ para instrucciones.\n";
    exit(1);
}

// Crear composer.json si no existe
if (!file_exists('composer.json')) {
    $composer_json = [
        'require' => [
            'phpmailer/phpmailer' => '^6.8'
        ]
    ];
    
    file_put_contents('composer.json', json_encode($composer_json, JSON_PRETTY_PRINT));
    echo "Archivo composer.json creado.\n";
}

// Ejecutar composer install
echo "Ejecutando composer install...\n";
$output = shell_exec('composer install');
echo $output;

// Verificar si la instalación fue exitosa
if (file_exists('vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    echo "PHPMailer instalado correctamente.\n";
    
    // Crear archivo de prueba
    $test_file = <<<'EOT'
<?php
// Archivo de prueba para verificar la instalación de PHPMailer

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "PHPMailer está instalado correctamente.\n";
echo "Versión: " . PHPMailer::VERSION . "\n";

// Para probar el envío de correos, descomenta y configura lo siguiente:
/*
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'tu_correo@gmail.com';
    $mail->Password = 'tu_contraseña_de_aplicacion';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    $mail->setFrom('tu_correo@gmail.com', 'ToolSoft');
    $mail->addAddress('destinatario@ejemplo.com');
    
    $mail->isHTML(true);
    $mail->Subject = 'Prueba de PHPMailer';
    $mail->Body = 'Este es un correo de prueba enviado con PHPMailer.';
    
    $mail->send();
    echo "Correo enviado correctamente.\n";
} catch (Exception $e) {
    echo "Error al enviar correo: {$mail->ErrorInfo}\n";
}
*/
EOT;
    
    file_put_contents('test_phpmailer.php', $test_file);
    echo "Archivo de prueba creado: test_phpmailer.php\n";
    echo "Ejecuta 'php test_phpmailer.php' para verificar la instalación.\n";
} else {
    echo "Error: No se pudo instalar PHPMailer correctamente.\n";
    exit(1);
}

echo "\nPara configurar el envío de correos, edita el archivo checkout.php y actualiza las siguientes líneas:\n";
echo "- \$mail->Username = 'tu_correo@gmail.com';\n";
echo "- \$mail->Password = 'tu_contraseña_de_aplicacion';\n";
echo "- \$mail->setFrom('tu_correo@gmail.com', 'ToolSoft');\n\n";
echo "Nota: Si usas Gmail, necesitarás generar una contraseña de aplicación en:\n";
echo "https://myaccount.google.com/apppasswords\n";
?>