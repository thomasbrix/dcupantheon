/**
 * @file
 * Marker Scroll to Result.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Recenter control.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMarkerScrollToId = {
    attach: function (context, drupalSettings) {
      Drupal.geolocation.executeFeatureOnAllMaps(
        'geolocation_marker_scroll_to_id',

        /**
         * @param {GeolocationMapInterface} map
         * @param {GeolocationMapFeatureSettings} featureSettings
         */
        function (map, featureSettings) {
          map.addMarkerAddedCallback(function (marker) {
            marker.addListener('click', function () {
              var id = marker.locationWrapper.data('scroll-target-id').split('\n').join('');
              var target = $('#' + id + ':visible').first();
              // if (target.length === 1) {
                $('html, body').animate({
                  scrollTop: target.offset().top - 200
                }, 'slow');
              // }

            });
          });
          return true;
        },
        drupalSettings
      );
    },
    detach: function (context, drupalSettings) {}
  };

})(jQuery, Drupal);

(function ($, Drupal) {

  'use strict';

  /**
   * Google MarkerIcon.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches common map style functionality to relevant elements.
   */
  Drupal.behaviors.geolocationMarkerHoverAnchor = {
    attach: function (context, drupalSettings) {
      $('.geolocation-marker-hover').once('geolocation-marker-hover-anchor').hover(function (e) {
        var markerAnchor = $(this).children('a').attr('href').split('#').pop();

        Drupal.geolocation.executeFeatureOnAllMaps(
          'marker_zoom_to_animate',

          /**
           * @param {GeolocationGoogleMap} map - Current map.
           * @param {MarkerIconSettings} featureSettings - Settings for current feature.
           */
          function (map, featureSettings) {
            $.each(map.mapMarkers, function (index, marker) {
              var mapmarker = marker.locationWrapper.data('marker-zoom-anchor-id').split('\n').join('');
              // console.log(mapmarker);
              //if (marker.locationWrapper.data('marker-zoom-anchor-id') === markerAnchor) {
              if (mapmarker === markerAnchor) {
                marker.setAnimation(google.maps.Animation.BOUNCE);
                window.setTimeout(function () {
                  marker.setAnimation(null);
                }, 1000);
              }
            });

            return false;
          },
          drupalSettings
        );

      }, function (e) {

        var markerAnchor = $(this).children('a').attr('href').split('#').pop();

        Drupal.geolocation.executeFeatureOnAllMaps(
          'marker_zoom_to_animate',

          /**
           * @param {GeolocationGoogleMap} map - Current map.
           * @param {MarkerIconSettings} featureSettings - Settings for current feature.
           */
          function (map, featureSettings) {
            $.each(map.mapMarkers, function (index, marker) {
              if (marker.locationWrapper.data('marker-zoom-anchor-id') === markerAnchor) {
                var intialIcon = marker.icon;
                if (intialIcon.includes('marker-price-hover.png')) {
                  var iconHover = intialIcon.replace('marker-price-hover.png', 'marker-price.png');
                  marker.setIcon(iconHover);
                }
              }
            });
            return false;
          },
          drupalSettings
        );
      });
    },
    detach: function (context, drupalSettings) {}
  };
})(jQuery, Drupal);
