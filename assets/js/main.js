// ===== INSTALLMENT BUSINESS - CUSTOM THEME =====

$(document).ready(function () {

  // Sidebar open/close (hamburger #sidebarToggle, drawer, localStorage) is handled in assets/js/pos-ui.js

  // ===== SIDEBAR COLLAPSE ARROW =====
  $('.sidebar .nav-link[data-toggle="collapse"]').click(function (e) {
    var target = $($(this).attr('href'));
    $(this).find('.arrow i').toggleClass('fa-chevron-right fa-chevron-down');
  });

  // ===== AUTO-EXPAND ACTIVE SUBMENU =====
  $('.collapse-item.active').each(function () {
    var collapse = $(this).closest('.collapse');
    if (collapse.length) {
      collapse.addClass('show');
      var toggler = $('[href="#' + collapse.attr('id') + '"]');
      if (toggler.length) {
        toggler.find('.arrow i').removeClass('fa-chevron-right').addClass('fa-chevron-down');
      }
    }
  });

  // ===== SCROLL TO TOP =====
  $(window).scroll(function () {
    if ($(this).scrollTop() > 200) {
      $('#scrollToTop').addClass('show');
    } else {
      $('#scrollToTop').removeClass('show');
    }
  });

  $('#scrollToTop').click(function (e) {
    e.preventDefault();
    $('html, body').animate({ scrollTop: 0 }, 300);
  });

  // ===== AUTO-DISMISS ALERTS =====
  setTimeout(function () {
    $('.alert-dismissible').fadeOut('slow');
  }, 5000);
});







