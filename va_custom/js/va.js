(function ($, once) {

  Drupal.behaviors.selectedItems = {
    selected: [],
    attach(context) {
      const elements = once('selectedItems', 'div.pv-wrapper div', context);
      // Bind the `processSelectedItems` function to the `Drupal.behaviors.selectedItems` object.
      elements.forEach(processSelectedItems.bind(this));
    }
  };

  function processSelectedItems(element) {
    const valuesLimit = 10;
    const inputElement = $(element).find('input');
    $(element).on('click', function(e) {
      e.preventDefault();
      let toggledItems = Drupal.behaviors.selectedItems.selected;
  
      const popupFixed = $('.div-selected-popup .fieldset__wrapper');
  
      $(this).toggleClass("selected");
  
      const inputId = inputElement.attr('id');
      const inputName = inputElement.attr('name');
      // Convert the first letter to uppercase.
      let inputNameFirstUpper = inputName.charAt(0).toUpperCase() + inputName.slice(1);

      // Confirm if the array don't already have the class to avoid duplicated items.
      // Use the full path to selectedItems to avoid empty arrays.
      if ($(this).hasClass("selected") && !toggledItems.includes(inputElement.data('id'))) {
        toggledItems.push(inputElement.data('id'));
        const id = inputElement.attr('data-id');
        inputElement.val(id);


        // Replace _ to blank space on name.
        if (inputNameFirstUpper.indexOf('_') > -1) {
          // Remove the under score to show on selected popup.
          inputNameFirstUpper = inputNameFirstUpper.replace(/_/g, ' ');
        } 

        if (toggledItems.length <= 10) {
          // Add new item on popup.  
          popupFixed.append('<div id=' + inputId + ' class="current-selection">' + inputNameFirstUpper + '</div>');
          $('.div-selected-popup').removeClass('popup-invisible');
        }

      } 
      else {
        const index = toggledItems.indexOf(inputElement.data('id'));
        if (index > -1) {
          toggledItems.splice(index, 1);
          inputElement.val('');
          // Remove the unselected item on popup.
          $('.div-selected-popup .fieldset__wrapper').find('#' + inputId).remove();
        }
      }
  
      // Turn invisible the selecte pop if is empty.
      if (toggledItems.length == 0) {
        $('.div-selected-popup').addClass('popup-invisible');
      }
  
      // Enable or disable the submit button based on the number of selected items.
      const submitButton = $('input[name="pva-submit"]');
      if (toggledItems.length == valuesLimit) {
        submitButton.removeAttr("disabled").removeClass("is-disabled");
        evalutionPopUp(drupalSettings.evalutionPopUpTitle, drupalSettings.evalutionPopUpText);
      } 
      else {
        submitButton.attr("disabled", "disabled").addClass("is-disabled");
      }
    });
  }

  Drupal.behaviors.changeLanguage = {
    attach: function () {
      if (!drupalSettings.changeLanguage) {
        // Get all links on the page
        const links = document.querySelectorAll('.primary-nav__menu a');
        // Add click event listener to each link
        links.forEach(link => {
          link.addEventListener('click', event => {
            // Get the label link
            const langcode = getLangCode();
            const linkLabel = link.textContent.toLowerCase().trim();
            if (linkLabel == 'home') {
              // Solve the issue on homepage to change lang without /.
              if (link.getAttribute('href') == '/pt-br') {
                link.setAttribute('href', '/pt-br/');
              }
              return;
            }
            // Prevent default behavior of link click
            event.preventDefault();

            const url = new URL(window.location.href);
            // Get the various parts of the URL using the URL object's properties
            const protocol = url.protocol; // "https:"
            const hostname = url.hostname; // "www.example.com"
            const pathname = url.pathname; // "/path/to/file.html"
            const searchQuery = url.search; // "?query=string"
            const parts = pathname.split('/');
            const lastPart = parts[parts.length - 1];
            // Avoid the reload when the language is the same as current.
            if (linkLabel == langcode) {
              return;
            }

            // Rebuild the url after each click.
            // If don't have query, we are on homepage.
            if (linkLabel == 'en' && searchQuery.length == 0 && lastPart.length == 0) {
              window.location.href = '/';
            }
            // If has query we are on another page that we need to keep the query and etc.
            else if (linkLabel == 'en') {
              window.location.href = '/' + lastPart + searchQuery;
            }
            // If the lang is different than en.
            else {
              window.location.href = '/' + linkLabel + '/' + lastPart + searchQuery;
            }
          });
        });
        drupalSettings.changeLanguage = true;
      }
    }
  };

  Drupal.behaviors.setLimitingFactorOnValues = {
    attach: function () {
      $(document).ready(function() {
        if (!drupalSettings.setLimitingFactorOnValues) {
          $('div[role="limiting-factor"]').each(function() {
            const valueItem = $(this).find('.container-values').children('.values-inline');
            $(valueItem).css("background-image", ""); 
            $(this).addClass('limiting-factor-class');
          });
        }
        drupalSettings.setLimitingFactorOnValues = true;
      });
    }
  };

  Drupal.behaviors.setValuePositionOnResult = {
    attach: function () {
      $(document).ready(function() {
        if (!drupalSettings.setValuePositionOnResult) {
          $('.triangle-title').each(function() {
            const valueItem = $(this).find('.container-values').children('.values-inline');
            const consciousness = $(this).find('.container-values').attr('id');
            const valueItemLength = valueItem.length;
            if (consciousness == 'evolution') {
              if (valueItemLength == 1) {
                valueItem.eq(0).addClass('one-item-evo');
              }
              else if (valueItemLength == 2) {
                valueItem.eq(0).addClass('two-item-one-evo');
                valueItem.eq(1).addClass('two-item-two-evo');
              }
              else if (valueItemLength == 3) {
                valueItem.eq(0).addClass('three-item-one-evo');
                valueItem.eq(1).addClass('three-item-two-evo');
                valueItem.eq(2).addClass('three-item-three-evo');
              }
              else if (valueItemLength == 4) {
                valueItem.eq(0).addClass('four-item-one-evo');
                valueItem.eq(1).addClass('four-item-two-evo');
                valueItem.eq(2).addClass('four-item-three-evo');
                valueItem.eq(3).addClass('four-item-four-evo');
              }
              else if (valueItemLength == 5) {
                valueItem.eq(0).addClass('five-item-one-evo');
                valueItem.eq(1).addClass('five-item-two-evo');
                valueItem.eq(2).addClass('five-item-three-evo');
                valueItem.eq(3).addClass('five-item-four-evo');
                valueItem.eq(4).addClass('five-item-five-evo');
              }
              else if (valueItemLength == 6) {
                valueItem.eq(0).addClass('six-item-one-evo');
                valueItem.eq(1).addClass('six-item-two-evo');
                valueItem.eq(2).addClass('six-item-three-evo');
                valueItem.eq(3).addClass('six-item-four-evo');
                valueItem.eq(4).addClass('six-item-five-evo');
                valueItem.eq(5).addClass('six-item-six-evo');
              }
            }
            else if (consciousness == 'contribution' || consciousness == 'viability' ) {
              if (valueItemLength == 1) {
                valueItem.eq(0).addClass('one-item-cont-viab');
              }
              else if (valueItemLength == 2) {
                valueItem.eq(0).addClass('two-item-cont-viab-one');
                valueItem.eq(1).addClass('two-item-cont-viab-two');
              }
              else if (valueItemLength == 3) {
                valueItem.eq(0).addClass('three-item-cont-viab-one');
                valueItem.eq(1).addClass('three-item-cont-viab-two');
                valueItem.eq(2).addClass('three-item-cont-viab-three');
              }
              else if (valueItemLength == 4) {
                valueItem.eq(0).addClass('four-item-cont-viab-one');
                valueItem.eq(1).addClass('four-item-cont-viab-two');
                valueItem.eq(2).addClass('four-item-cont-viab-three');
                valueItem.eq(3).addClass('four-item-cont-viab-four');
              }
              else if (valueItemLength == 5) {
                valueItem.eq(0).addClass('five-item-cont-viab-one');
                valueItem.eq(1).addClass('five-item-cont-viab-two');
                valueItem.eq(2).addClass('five-item-cont-viab-three');
                valueItem.eq(3).addClass('five-item-cont-viab-four');
                valueItem.eq(4).addClass('five-item-cont-viab-five');
              }
              else if (valueItemLength == 6) {
                valueItem.eq(0).addClass('six-item-cont-viab-one');
                valueItem.eq(1).addClass('six-item-cont-viab-two');
                valueItem.eq(2).addClass('six-item-cont-viab-three');
                valueItem.eq(3).addClass('six-item-cont-viab-four');
                valueItem.eq(4).addClass('six-item-cont-viab-five');
                valueItem.eq(5).addClass('six-item-cont-viab-six');
              }
            }
            else {                                     
              if (valueItemLength == 1) {
                valueItem.eq(0).addClass('one-item');
              }
              else if (valueItemLength == 2) {
                valueItem.eq(0).addClass('two-items-one');
                valueItem.eq(1).addClass('two-items-two');
              }
              else if (valueItemLength == 3) {
                valueItem.eq(0).addClass('three-items-one');
                valueItem.eq(1).addClass('three-items-two');
                valueItem.eq(2).addClass('three-items-three');
              }
              else if (valueItemLength == 4) {
                valueItem.eq(0).addClass('four-items-one');
                valueItem.eq(1).addClass('four-items-two');
                valueItem.eq(2).addClass('four-items-three');
                valueItem.eq(3).addClass('four-items-four');
              }
              else if (valueItemLength == 5) {
                valueItem.eq(0).addClass('five-items-one');
                valueItem.eq(1).addClass('five-items-two');
                valueItem.eq(2).addClass('five-items-three');
                valueItem.eq(3).addClass('five-items-four');
                valueItem.eq(4).addClass('five-items-five');
              }
              else if (valueItemLength == 6) {
                valueItem.eq(0).addClass('six-items-one');
                valueItem.eq(1).addClass('six-items-two');
                valueItem.eq(2).addClass('six-items-three');
                valueItem.eq(3).addClass('six-items-four');
                valueItem.eq(4).addClass('six-items-five');
                valueItem.eq(5).addClass('six-items-six');
              }
            }
          });
        }
        drupalSettings.setValuePositionOnResult = true;
      });
    }
  };

  function getLangCode() {
    return drupalSettings.language;
  }

}(jQuery, once));

function evalutionPopUp(title, text) {
  return Swal.fire({
    title: title,
    text: text,
    icon: 'success',
    confirmButtonText: 'OK',
    confirmButtonColor: '#ed1941',
  });
}
