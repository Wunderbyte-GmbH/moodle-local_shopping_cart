// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
 * @package    local_shopping_cart
 * @copyright  Wunderbyte GmbH <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Url from 'core/url';
import {showNotification} from 'local_shopping_cart/notifications';
import ModalForm from 'core_form/modalform';

import {reinit} from 'local_shopping_cart/cart';

import {
    get_string as getString
        }
        from 'core/str';

export const init = (users, userid = 0) => {
    // eslint-disable-next-line no-console
    console.log('run init', userid);

    document.getElementById('checkout-tab').classList.remove('success');

    const buybuttons = document.querySelectorAll('.buy-btn');
        // eslint-disable-next-line no-console
        if (buybuttons) {
            buybuttons.forEach(buybutton => {
                buybutton.addEventListener('click', (e) => confirmPayment(userid, e.target.dataset.paymenttype));
            });
        }

    const checkoutbutton = document.querySelector('#checkout-btn');
    // eslint-disable-next-line no-console
    console.log(checkoutbutton);
    if (checkoutbutton) {
        checkoutbutton.addEventListener('click', function() {

            document.getElementById('checkout-tab').classList.add('success');
            // eslint-disable-next-line no-console
            console.log('click');
        });
    }
    autocomplete(document.getElementById("shopping-cart-searchuser"), users);
};

export const confirmPayment = (userid, paymenttype) => {
    Ajax.call([{
        methodname: "local_shopping_cart_confirm_cash_payment",
        args: {
            'userid': userid,
            'paymenttype': paymenttype
        },
        done: function(data) {
            if (data.status === 1) {
                // eslint-disable-next-line no-console
                console.log('payment confirmed', data);

                // The function can be called via cashier, or because a user pays via credits.
                // If that's the case, we are not on the cashier site.

                const oncashier = window.location.href.indexOf("cashier.php");

                // If we are not on cashier, we can just redirect.
                if (oncashier < 1) {

                    const identifier = data.identifier;

                    const newurl = Url.fileUrl("/local/shopping_cart/checkout.php?success=1&identifier=" + identifier, "");

                    location.href = newurl;

                } else {

                    // This is the cachier view.

                    // Set link to right receipt.
                    addPrintIdentifier(data.identifier, userid);

                    document.getElementById('success-tab').classList.add('success');

                    displayPaymentMessage('paymentsuccessful');


                }

            } else {

                // eslint-disable-next-line no-console
                console.log('payment denied');
                displayPaymentMessage('paymentdenied', false);
                document.getElementById('success-tab').classList.add('error');
            }
        },
        fail: function(ex) {

            displayPaymentMessage('paymentdenied', false);

            // eslint-disable-next-line no-console
            console.log(ex);
        },
    }]);
};

export const validateCart = ($userid) => {
    // eslint-disable-next-line no-alert
    alert($userid);
};

/**
 * Adds parameters to the printbutton.
 * @param {int} identifier
 * @param {int} userid
 */
export const addPrintIdentifier = (identifier, userid) => {
   let printbtn = document.getElementById('printbtn');
   let href = printbtn.getAttribute('href');
   printbtn.setAttribute('href', href + identifier + '&userid=' + userid);
};

/**
 * The autocomplete function takes two arguments.
 * The text field element and an array of possible autocompleted values.
 * @param {string} inp
 * @param {array} arr
 */
 export const autocomplete = (inp, arr) => {
    var currentFocus;
    const useridfield = document.querySelector('#useridfield');
    inp.addEventListener("input", function() {
        var a, b, i;
        let val = this.value;
        closeAllLists();
        if (!val) {
            return false;
        }
        currentFocus = -1;
        a = document.createElement("DIV");
        a.setAttribute("id", this.id + "autocomplete-list");
        a.setAttribute("class", "autocomplete-items");
        this.parentNode.appendChild(a);
        for (i = 0; i < arr.length; i++) {
            if (arr[i].toUpperCase().indexOf(val.toUpperCase()) > -1) {

                /* Make the matching letters bold: */
                let index = arr[i].toUpperCase().indexOf(val.toUpperCase());

                /* Create a DIV element for each matching element: */
                b = document.createElement("DIV");

                const namearray = arr[i].split(' ');
                const firstname = namearray[0].toLowerCase();
                const lastname = namearray[1].toLowerCase();
                const email = namearray[2].toLowerCase().replace(/^\(/, '').replace(/\)$/, '');
                const userid = namearray[3].toLowerCase().replace(/^uid:/, '');

                b.dataset.firstname = firstname;
                b.dataset.lastname = lastname;
                b.id = 'li_' + firstname + '_' + lastname;
                b.dataset.email = email;
                b.dataset.userid = userid;

                b.innerHTML = arr[i].substr(0, index);
                b.innerHTML += "<strong>"
                        + arr[i].substr(arr[i].toUpperCase().indexOf(val.toUpperCase()), val.length) + "</strong>";
                b.innerHTML += arr[i].substr(index + val.length);
                /* Insert an input field that will hold the current array item's value: */
                b.innerHTML += "<input type='hidden' value='" + arr[i] + "'>";
                b.addEventListener("click", function() {
                    inp.value = this.getElementsByTagName("input")[0].value;
                    useridfield.value = this.getElementsByTagName("input")[0].value.split('uid:')[1];
                    closeAllLists();
                });
                a.appendChild(b);
            }
        }
        return null;
    });

    inp.addEventListener("keydown", function(e) {
        var x = document.getElementById(this.id + "autocomplete-list");
        if (x) {
            x = x.getElementsByTagName("div");
        }
        if (e.keyCode === 40) {
          currentFocus++;
          addActive(x);
        } else if (e.keyCode === 38) {
          currentFocus--;
          addActive(x);
        } else if (e.keyCode === 13) {
          e.preventDefault();
          if (currentFocus > -1) {
            if (x) {
                x[currentFocus].click();
            }
          }
        }
    });

    /**
     * Add active.
     * @param {*} x
     */
    function addActive(x) {
        if (!x) {
            return;
        }
        removeActive(x);
        if (currentFocus >= x.length) {
            currentFocus = 0;
        }
        if (currentFocus < 0) {
            currentFocus = (x.length - 1);
        }
        x[currentFocus].classList.add("autocomplete-active");
    }

    /**
     * Remove active.
     * @param {*} x
     */
    function removeActive(x) {
        for (var i = 0; i < x.length; i++) {
            x[i].classList.remove("autocomplete-active");
        }
    }

    /**
     * Close all list elements.
     * @param {*} elmnt
     */
    function closeAllLists(elmnt) {
        var x = document.getElementsByClassName("autocomplete-items");
        for (var i = 0; i < x.length; i++) {
            if (elmnt !== x[i] && elmnt !== inp) {
            x[i].parentNode.removeChild(x[i]);
            }
        }
    }
    document.addEventListener("click", function(e) {
        closeAllLists(e.target);
    });
  };

/**
 *
 * @param {*} event
 */
export function discountModal(event) {

    // We two parents up, we find the right element with the necessary information.
    const element = event.target.closest('li');

    // eslint-disable-next-line no-console
    console.log('closest', element);

    const price = element.dataset.price;
    const itemid = element.dataset.itemid;
    const userid = element.dataset.userid;
    const componentname = element.dataset.component;

    // eslint-disable-next-line no-console
    console.log('discountModal', price, itemid, userid, componentname);

    const modalForm = new ModalForm({

        // Name of the class where form is defined (must extend \core_form\dynamic_form):
        formClass: "local_shopping_cart\\form\\modal_add_discount_to_item",
        // Add as many arguments as you need, they will be passed to the form:
        args: {'price': price,
               'itemid': itemid,
               'userid': userid,
               'componentname': componentname},
        // Pass any configuration settings to the modal dialogue, for example, the title:
        modalConfig: {title: getString('applydiscount', 'local_shopping_cart')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: element
    });
    // Listen to events if you want to execute something on form submit.
    // Event detail will contain everything the process() function returned:
    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, (e) => {
        const response = e.detail;
        // eslint-disable-next-line no-console
        console.log('confirmCancelAndSetCreditModal response: ', response);

        reinit(-1);
    });

    // Show the form.
    modalForm.show();

}

/**
 * This function first displays the result at the right place.
 * It further uses the notification class to make result even more visible.
 * @param {string} message
 * @param {bool} success
 */
function displayPaymentMessage(message, success = true) {
    let displaymessage = document.querySelector('.payment_message_result');
    if (displaymessage) {
        getString(message, 'local_shopping_cart').then(localizedmessage => {

            displaymessage.innerText = localizedmessage;

            if (success) {
                showNotification(localizedmessage, "info");
            } else {
                showNotification(localizedmessage, "error");
            }
            return;
        }).catch(e => {

            // eslint-disable-next-line no-console
            console.log(e);
        });
    }
}
