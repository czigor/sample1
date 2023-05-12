/**
 * @file
 * JS for the story page.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.piliskorStory = {
    attach: function (context, settings) {
      $('.story-text').click(function() {
        var class_name = $(this).attr("data-popup-class");
        var popupContents = $("." + class_name)[0].outerHTML;
        Drupal.dialog(popupContents, {
          title: Drupal.t('This text snippet is visible thanks to:'),
          closeOnEscape: true,
          buttons: [{
            text: Drupal.t("Close"),
            click: function() {
              $( this ).dialog( "close" );
            },
          }]
        }).showModal();
      });
      $('.book-control .contributions input').prop( "checked", true );
      $('.book-control .contributions input').change(function() {
        $('.field--name-field-uids-for-css').toggle();
        $('.field--name-field-annotated-text').toggleClass('contributions');
        $('.story-contributors').toggleClass('contributions');
        if ($(this).prop("checked") == false) {
          $(".story-contributors li").removeClass("colorize");
          $(".story-text").removeClass("colorize");
        }
        else {
          $(".story-contributors li").addClass("colorize");
          $(".story-text").addClass("colorize");
        }
      });
      $('.story-contributors li').click(function() {
        if ($(this).hasClass("colorize")) {
          $("." + $(this).attr("data-uid")).removeClass("colorize");
        }
        else {
          $("." + $(this).attr("data-uid")).addClass("colorize");
        }
        $(this).toggleClass("colorize");
      });
      $('.field--name-field-uids-for-css .show-all').click(function() {
        $(".story-contributors li").addClass("colorize");
        $(".story-text").addClass("colorize");
      });
      $('.field--name-field-uids-for-css .hide-all').click(function() {
        $(".story-contributors li").removeClass("colorize");
        $(".story-text").removeClass("colorize");
      });
    }
  }

})(jQuery, Drupal, drupalSettings);
