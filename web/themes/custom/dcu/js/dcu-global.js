(function($) {
    /* In this function, $ is a reference to the jQuery global object. */
    Drupal.behaviors.dcu = {
      attach: function(context, settings) {
      }
    }

    $(document).on("click", '[data-toggle="lightbox"]', function(event) {
      event.preventDefault();
      $(this).ekkoLightbox();
    });

    //Country search solr - reset lat-long.
    // $(document).delegate('.solr-country-search','click', function() {
    //   console.log('Bingo');
    //   $('input[name="field_geo_location_boundary[lat_north_east]"]').val('');
    //   $('input[name="field_geo_location_boundary[lng_north_east]"]').val('');
    //   $('input[name="field_geo_location_boundary[lat_south_west]"]').val('');
    //   $('input[name="field_geo_location_boundary[lng_south_west]"]').val('');
    // });
    $(document).delegate('#views-exposed-form-solr-campsites-solr-campsites','click submit', function(e) {
      $('input[name="field_geo_location_boundary[lat_north_east]"]').val('');
      $('input[name="field_geo_location_boundary[lng_north_east]"]').val('');
      $('input[name="field_geo_location_boundary[lat_south_west]"]').val('');
      $('input[name="field_geo_location_boundary[lng_south_west]"]').val('');
    });

    //Close mega menu on escape.
    $(document).on('keyup', function(event) {
      if (event.key === 'Escape') {
        $( ".navigation__main" ).find( ".is-active-menu" ).removeClass( "is-active-menu");
      }
    });

    $('#fac-show').click(function() {
      $('.fac-hidden').show();
      $('.fac-show-link').hide();
    });

    $(document).delegate('form', 'submit', function(event) {
      if (!$('div.profile-submit').length) {
        $("#" + $(this).attr('id')).find(".disable-on-click").val(Drupal.t("Please wait..."));
        $("#" + $(this).attr('id')).find(".disable-on-click").before('<div class=\"profile-submit ajax-progress ajax-progress-throbber\"><div class=\"throbber\">&nbsp;</div></div>');
      }
    });
  }
)(jQuery);
