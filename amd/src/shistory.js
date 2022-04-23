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
import {
    get_string as getString,
    get_strings as getStrings
        }
        from 'core/str';
import Notification from 'core/notification';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

export const init = () => {

    const buttons = document.querySelectorAll("#shopping_cart-cashiers-section .shopping_cart_history_cancel_button");

        buttons.forEach(button => {

            if (!button.dataset.initialized) {

                // eslint-disable-next-line no-console
                console.log(button.dataset.canceled, button.dataset.historyid);

                if (button.dataset.canceled == true) {
                    setButtonToCanceled(button);
                } else {
                    button.addEventListener('click', event => {

                        event.preventDefault();
                        event.stopPropagation();

                        if (button.dataset.canceled == false) {
                            // eslint-disable-next-line no-console
                            console.log('button clicked');

                            confirmDecisionModal(button);

                        }

                    });
                }
                button.dataset.initialized = true;
            }
        });
};

/**
 * This triggers the ajax call to acutally cancel the purchase.
 * @param {int} itemid
 * @param {int} userid
 * @param {string} componentname
 * @param {int} historyid
 * @param {type} button
 */
function cancelPurchase(itemid, userid, componentname, historyid, button) {

    // eslint-disable-next-line no-console
    console.log('button clicked', historyid);

    Ajax.call([{
        methodname: "local_shopping_cart_cancel_purchase",
        args: {
            'itemid': itemid,
            'componentname': componentname,
            'userid': userid,
            'historyid': historyid
        },
        done: function(data) {

            if (data.success == 1) {

                getString('cancelsuccess', 'local_shopping_cart').then(message => {

                    Notification.addNotification({
                        message,
                        type: "success"
                    });

                    setTimeout(() => {
                        let notificationslist = document.querySelectorAll('#user-notifications div.alert');
                        const notificatonelement = notificationslist[notificationslist.length - 1];
                        notificatonelement.remove();
                    }, 5000);

                    return;
                }).catch(e => {
                    // eslint-disable-next-line no-console
                    console.log(e);
                });

                // eslint-disable-next-line no-console
                console.log('data returned', data.success);
                setButtonToCanceled(button);
            } else {
                getString('canceldidntwork', 'local_shopping_cart').then(message => {

                    Notification.addNotification({
                        message,
                        type: "danger"
                    });

                    setTimeout(() => {
                        let notificationslist = document.querySelectorAll('#user-notifications div.alert');
                        const notificatonelement = notificationslist[notificationslist.length - 1];
                        notificatonelement.remove();
                    }, 5000);

                    return;
                }).catch(e => {
                    // eslint-disable-next-line no-console
                    console.log(e);
                });
            }

        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log("ex:" + ex);
        },
    }]);

}

/**
 * Function to change classes and text of button.
 * @param {*} button
 */
function setButtonToCanceled(button) {

    button.classList.add('disabled');
    button.classList.remove('btn-primary');
    button.classList.add('btn-danger');
    button.dataset.canceled = true;

    getString('canceled', 'local_shopping_cart').then(result => {
        // eslint-disable-next-line no-console
        console.log(result);

        button.innerText = result;
        return;
    }).catch(e => {
        // eslint-disable-next-line no-console
        console.log(e);
    });
}

/**
 *
 * @param {*} button
 */
function confirmDecisionModal(button) {

    getStrings([
            {key: 'confirmcanceltitle', component: 'local_shopping_cart'},
            {key: 'confirmcancelbody', component: 'local_shopping_cart'},
            {key: 'cancelpurchase', component: 'local_shopping_cart'}
        ]
        ).then(strings => {

            ModalFactory.create({type: ModalFactory.types.SAVE_CANCEL}).then(modal => {

                modal.setTitle(strings[0]);
                    modal.setBody(strings[1]);
                    modal.setSaveButtonText(strings[2]);
                    modal.getRoot().on(ModalEvents.save, function() {

                        // eslint-disable-next-line no-console
                        console.log('we saved');

                        const historyid = button.dataset.historyid;
                        const itemid = button.dataset.itemid;
                        const userid = button.dataset.userid;
                        const componentname = button.dataset.componentname;

                        cancelPurchase(itemid, userid, componentname, historyid, button);
                    });

                    modal.show();
                    return modal;
            }).catch(e => {
                // eslint-disable-next-line no-console
                console.log(e);
            });
            return true;
        }).catch(e => {
            // eslint-disable-next-line no-console
            console.log(e);
        });
}