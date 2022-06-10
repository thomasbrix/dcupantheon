(function ($, Drupal) {
  Drupal.behaviors.dcu_member_profile = {
    attach: function (context, settings) {

      $("#consentmodalcnt").once('confirm_consent').on("click", "#usrconfirmconsent", function(event) {
        event.preventDefault();
        var posting = $.post("/confirmconsent", { });
        posting.done(function(data) {
          jQuery("#consentmodal").modal("hide");
        });
        posting.fail(function() {
          console.log("There was an error posting consent to backend");
        });
      });

      $("#consentmodalcnt").once('close_consent').on("click", "#consentModalClose", function(event) {
        event.preventDefault();
        Cookies.set('consentdefer', true, { expires: 30 });
        $("#consentmodal").modal("hide");
      });

      $(document, context).once('get_consent').each( function() {
        var dcuconsent = function(response) {
          if (response.show) {
            $("#consentmodalcnt").html(response.data);
            $("#consentmodal").modal("show");
          }
        };
        if (settings.dcu_member.presentconsent && (typeof Cookies.get('consentdefer')  === 'undefined')) {
          $.get("/userconsent", null, dcuconsent);
        }
      });
    },
  };
}(jQuery, Drupal));

