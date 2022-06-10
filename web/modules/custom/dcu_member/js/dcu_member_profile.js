(function ($, Drupal) {
  Drupal.behaviors.dcu_member_profile = {
    attach: function (context, settings) {
      $("#add-child-btn", context).on("click", function (e) {
        e.preventDefault();
        var nextChild = $(this).attr("data-next-child");
        var nextChildElement = "#child-" + nextChild;
        $(nextChildElement).toggle();
        nextChild++;
        nextChildElement = "#child-" + nextChild;
        if ($(nextChildElement).length) {
          $(this).attr("data-next-child", nextChild);
        }
        else {
          $(this).attr("disabled", "disabled");
        }
      });
    },
  };
}(jQuery, Drupal));

