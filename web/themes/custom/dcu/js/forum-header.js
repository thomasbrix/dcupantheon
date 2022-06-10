(function ($) {
  // This file is only loaded for the forum, which is not Drupal and therefore
  // doesn't know behaviors. Also the forum isn't responsive.
  /**
   * Header search form
   */
  $('#views-exposed-form-search-search').live('submit', function(event){
    var value=$.trim($("#edit-search").val());

    if ($(this).hasClass('expanded') && value.length > 0) {
      return true;
    }
    else if ($(this).hasClass('expanded') && value.length === 0) {
      $('#views-exposed-form-search-search').removeClass('expanded');
      return false;
    }
    else {
      $(this).addClass('expanded');
      $(".user-login-block .logout").find('a').slideUp(400);
      return false;
    }
  });

  /**
   * User login block
   */

  $('.user-login-block .login').live('click', function(event) {
    event.preventDefault();
    $(".user-login-block").find('form').slideToggle(400);
  });

  $('.user-login-block .logout').live('click', function(event) {
    if (event.target == this)
      $(this).find('a').slideToggle(400);
  });

  /**
   * Service menu
   */

  $('.service-menu-wrapper > ul > li > a').live('click', function(event) {
    event.preventDefault();

    if ($('.service-menu-wrapper > ul > li').hasClass('active')) {
      $('.service-menu-wrapper > ul > li').removeClass('active');
    }

    $(this).parent().addClass('active');
  });

  /**
   * Tablet main menu
   */

  $('.main-menu-wrapper > ul > li > a').live('click', function(event) {

    vw = $(window).width();
    if (!$(this).parent().find('ul.menu').length > 0) {
      return true;
    }
    else if(vw < 946 && !$(this).parent().hasClass('active')) {
      $.each($('.main-menu-wrapper > ul > li'), function(index, val) {
        $(this).removeClass('active');
      });
      $(this).parent().addClass('active')
      event.preventDefault();
      return false;
    }
    else if(vw < 946 && $(this).parent().hasClass('active')) {
      return true;
    }

  });

  /**
   * Close menus onclick
   */

  $(document).mouseup(function (e) {
    var container = $(".service-menu-wrapper");
    // if the target of the click isn't the container or a descendant of the
    // container
    if (!container.is(e.target) && container.has(e.target).length === 0) {
      $('.service-menu-wrapper > ul > li').removeClass('active');
    }

    var container2 = $(".main-menu-wrapper");
    vw = $(window).width();
    // if the target of the click isn't the container or a descendant of the
    // container
    if (!container2.is(e.target) && container2.has(e.target).length === 0 && vw < 946) {
      $('.main-menu-wrapper > ul > li').removeClass('active');
    }

    var container3 = $(".search-form-header");
    vw = $(window).width();
    // if the target of the click isn't the container or a descendant of the
    // container
    if (!container3.is(e.target) && container3.has(e.target).length === 0) {
      $('#views-exposed-form-search-search').removeClass('expanded');
    }

    var container4 = $(".user-login-block");
    vw = $(window).width();
    // if the target of the click isn't the container or a descendant of the
    // container
    if (!container4.is(e.target) && container4.has(e.target).length === 0) {
      $('.user-login-block').find('form').slideUp();
      $('.user-login-block .logout').find('a').slideUp();
    }
  });

})(jQuery);
