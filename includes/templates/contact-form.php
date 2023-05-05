<?php if(get_plugin_options('contact_plugin_active')):?>
  <!-- If form is activated in the backend, show form -->

  <div id="form-success" style="display: none;">
    <div id="success-message" class="alert alert-success">
      <!--Your message was sent successfully!-->
    </div>
    <a href="<?php echo home_url($_SERVER['REQUEST_URI']);?>" style="text-decoration: underline;">
      Submit again
    </a>
  </div>
  <div id="form-error" class="alert alert-danger" style="display: none;">
    There was an error submitting your form. Please try again.
  </div>

  <form id="contact-form">
    <?php wp_nonce_field('wp_rest');?>

    <div class="form-group mb-3">
      <label class="mb-1">Name</label>
      <input
        type="text"
        name="name"
        placeholder="John Doe"
        class="form-control"
        required
        >
    </div>
    <div class="form-group mb-3">
      <label class="mb-1">Email</label>
      <input
        type="email"
        name="email"
        placeholder="your@email.com"
        class="form-control"
        required
        >
    </div>
    <div class="form-group mb-3">
      <label class="mb-1">Phone</label>
      <input
        type="tel"
        name="phone"
        placeholder="(123) 456-7890"
        class="form-control"
        >
    </div>
    <div class="form-group mb-3">
      <label class="mb-1">Message</label>
      <textarea
        class="form-control"
        name="message"
        placeholder="Type your message here"
        style="height: 100px"
        required
        ></textarea>
    </div>
    <button
      type="submit"
      class="btn btn-primary btn-large w-100 mb-2">Submit</button>
  </form>

  <script>
    jQuery(document).ready(function($){
      $("#contact-form").submit(function(event){
        event.preventDefault();
        var form = $(this);
        
        $.ajax({
          type: "POST",
          url: "<?php echo get_rest_url(null, 'v1/contact-form/submit');?>",
          data: form.serialize(),
          success: function(res) {
            form.hide();
            $("#success-message").html(res);
            $("#form-success").fadeIn();
          },
          error: function() {
            $("#form-error").fadeIn();
          }
        })
      });
    });
  </script>
<?php else:?>
  This form is not active.
<?php endif;?>