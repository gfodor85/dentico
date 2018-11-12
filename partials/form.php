
<!-- FORM -->
<div class="container form-container" id="contact">
<h2>Do you have any question? Leave Us a message!</h2>

<div class="row justify-content-center">
    <div class="col-md-6 col-md-offset-3">

        <form id="contact-form" method="post" action="assets/php/contact-form/handler.php">

            <div class="from-group">
                <input class="form-control" type="text" name="name" placeholder="Please add your name" required>
            </div><br>
            <div class="from-group">
                <input class="form-control" type="text" name="subject" placeholder="Subject" required>
            </div><br>
            <div class="from-group">
                <input class="form-control" type="email" name="email" placeholder="Please add your email address" required>
            </div><br>
            <div class="from-group">
                <textarea class="form-control" name="message" id="message" rows="5" placeholder="Your Message" required></textarea>
            </div><br>

            <button class="btn btn-form" type="submit" name="send">Send Message</button>
            
        </form>


    </div>
</div>
</div>
