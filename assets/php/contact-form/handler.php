<!-- HANDLING -->
<script type="text/javascript">
    function countdown() {
        var i = document.getElementById("counter");
        if (parseInt(i.innerHTML)<=0) {
            location.href = "../../../index.php";
        }
        i.innerHTML = parseInt(i.innerHTML)-1;
    }
    setInterval(function(){ countdown(); },1000);
</script>';

<style>
<?php include '../../css/main.css'; ?>
</style>

<?php

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//Load composer's autoloader
require 'vendor/autoload.php';

$mail = new PHPMailer(true); // Passing `true` enables exceptions
try {
    if(isset($_POST['send'])){
            
        $name = $_POST['name'];
        $subject = $_POST['subject'];
        $email = $_POST['email'];
        $message = $_POST['message'];

        //Server settings
        //$mail->SMTPDebug = 2;                                 // Enable verbose debug output
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = '';                 // SMTP username
        $mail->Password = '';                           // SMTP password
        $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
        $mail->Port = 587;                                 // TCP port to connect to

        //Recipients
        $mail->setFrom($email, $name);
        $mail->addAddress('Your Email Address', 'Your Name');     // Add a recipient
        $mail->addReplyTo($email, $name);

        //Content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Email = $email;
        $mail->Body    = $message . "<br> Sent from georgefodor.com";

        $mail->send();
        echo
            '<div class="form-alert-body">
            <div class="form-alert-text">
                <p>Thank You! Your message has been sent. I will get back to you soon!</p><br>
                <svg class="mail_sent">
                    <use xlink:href="../../img/svg/symbols.svg#mail_sent"></use>
                </svg><br><br>
                <p>You will be redirected back to my website in <span id="counter">10</span> seconds</p><br>
            </div>
        </div>';
    }
    
} catch (Exception $e) {
    echo 
        '<div class="form-alert-body">
            <div class="form-alert-text">
                <p>Ooops! It seems something went wrong.</p><br>
                <p>Message could not be sent.</p><br>
                <p> Mailer Error: '. $mail->ErrorInfo .'</p>
                <svg class="error_msg">
                    <use xlink:href="../../img/svg/symbols.svg#error_msg"></use>
                </svg><br><br>
                <p>You will be redirected back to my website in <span id="counter">10</span> seconds</p><br>
            </div>
        </div>';
}
?>