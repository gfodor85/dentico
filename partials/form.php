<form class="dc-contact__form" id="contact-form" method="post" action="assets/php/contact-form/handler.php">
    <h3>Send us a message</h3>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['contact_csrf_token'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <div class="dc-field">
        <label for="contact-name">Your name</label>
        <input id="contact-name" name="name" type="text" maxlength="120" autocomplete="name" required>
    </div>
    <div class="dc-field">
        <label for="contact-subject">Subject</label>
        <input id="contact-subject" name="subject" type="text" maxlength="200" required>
    </div>
    <div class="dc-field">
        <label for="contact-email">Email address</label>
        <input id="contact-email" name="email" type="email" maxlength="254" autocomplete="email" required>
    </div>
    <div class="dc-field">
        <label for="contact-message">Your message</label>
        <textarea id="contact-message" name="message" rows="5" maxlength="5000" required></textarea>
    </div>
    <p class="dc-form-hint">Please do not include sensitive medical information. For an appointment request, use the booking form.</p>
    <button class="dc-btn" type="submit" name="send" value="1">Send message <span aria-hidden="true">&rarr;</span></button>
</form>
