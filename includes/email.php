<?php
/**
 * includes/email.php
 * -----------------------------------------------------------------
 * Envio de e-mails com PHPMailer. A função retorna false quando o
 * Composer ou a configuração SMTP ainda não estiverem disponíveis,
 * evitando que o restante do sistema seja interrompido por um fatal.
 * -----------------------------------------------------------------
 */

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envia um e-mail pelo SMTP configurado no SmartControl.
 */
function enviarEmail(
    string $destinatarioEmail,
    string $destinatarioNome,
    string $assunto,
    string $conteudoHtml,
    string $conteudoTexto = ''
): bool {
    try {
        $autoload = __DIR__ . '/../vendor/autoload.php';

        if (!class_exists(PHPMailer::class)) {
            if (!file_exists($autoload)) {
                error_log(
                    'PHPMailer não encontrado. Execute "composer install" na raiz do SmartControl.'
                );

                return false;
            }

            require_once $autoload;
        }

        $configEmail = require __DIR__ . '/../config/email.php';

        $camposObrigatorios = [
            'host',
            'porta',
            'usuario',
            'senha',
            'remetente_email',
            'remetente_nome',
        ];

        foreach ($camposObrigatorios as $campo) {
            if (!array_key_exists($campo, $configEmail)) {
                throw new RuntimeException(
                    'Configuração SMTP incompleta. Campo ausente: ' . $campo
                );
            }
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $configEmail['host'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $configEmail['usuario'];
        $mail->Password = (string) $configEmail['senha'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) $configEmail['porta'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            (string) $configEmail['remetente_email'],
            (string) $configEmail['remetente_nome']
        );

        $mail->addAddress($destinatarioEmail, $destinatarioNome);
        $mail->isHTML(true);
        $mail->Subject = $assunto;
        $mail->Body = $conteudoHtml;
        $mail->AltBody = $conteudoTexto !== ''
            ? $conteudoTexto
            : trim(strip_tags($conteudoHtml));

        $mail->send();

        return true;
    } catch (Throwable $e) {
        error_log('Erro ao enviar e-mail SmartControl: ' . $e->getMessage());

        return false;
    }
}
