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


export const buttoninit = (id, componentname) => {

    // eslint-disable-next-line no-console
    console.log('initialized', id, componentname);

    // First we get the button and delete the helper-span to secure js loading.
    const addtocartbutton = document.querySelector('#btn-' + id);

    addtocartbutton.querySelector('.loadJavascript').remove();
    addtocartbutton.querySelector('span').classList.remove('hidden');

    addtocartbutton.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        addItem(id, componentname);
    });
};

/**
 * Gets called from mustache template.
 *
 */
 export const init = () => {
    document.querySelectorAll('.shopping-cart-items [id^=item]').forEach(listitem => {
        // eslint-disable-next-line no-console
        console.log(listitem.dataset.expirationdate + " asd a" + listitem.dataset.id);
        setTimer(listitem.dataset.expirationdate, listitem.dataset.id);
    });
    document.querySelectorAll('.fa-trash-o').forEach(item => {
        addDeleteevent(item);
    });

    document.querySelector('#nav-shopping_cart-popover-container .btn-primary.addrandom').addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        addItem();
    });
};

export const deleteItem = (id) => {
    Ajax.call([{
        methodname: "local_shopping_cart_delete_item",
        args: {
            'id': id,
        },
        done: function() {
            // eslint-disable-next-line no-console
            console.log(id);
            let item = document.querySelector('#item-' + id);
            if (item) {
                item.remove();
                let itemcount1 = document.getElementById("countbadge");
                let itemcount2 = document.getElementById("itemcount");
                itemcount1.innerHTML = itemcount1.innerHTML > 0 ? itemcount1.innerHTML -= 1 : itemcount1.innerHTML;
                itemcount2.innerHTML = itemcount2.innerHTML > 0 ? itemcount2.innerHTML -= 1 : itemcount1.innerHTML;
                itemcount2.innerHTML = itemcount2.innerHTML == 0 ? itemcount2.classList.add("hidden") : itemcount2.innerHTML;
                let itemprice = item.dataset.price;
                let total = document.getElementById('totalprice');
                total = total == "undefined" ? 0 : total;
                total.innerHTML -= itemprice;
            }
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log(id, ex);
            let item =  document.querySelector('#item-' + id);
            if (item) {
                item.remove();
                let itemcount1 = document.getElementById("countbadge");
                let itemcount2 = document.getElementById("itemcount");
                itemcount1.innerHTML = itemcount1.innerHTML > 0 ? itemcount1.innerHTML -= 1 : itemcount1.innerHTML;
                itemcount2.innerHTML = itemcount2.innerHTML > 0 ? itemcount2.innerHTML -= 1 : itemcount1.innerHTML;
                itemcount2.innerHTML = itemcount2.innerHTML == 0 ? itemcount2.classList.add("hidden") : itemcount2.innerHTML;
                let itemprice = item.dataset.price;
                let total = document.getElementById('totalprice');
                total = total == "undefined" ? 0 : total;
                total.innerHTML -= itemprice;
            }
        },
    }]);
};

export const addItem = (id, componentname) => {
    Ajax.call([{
        methodname: "local_shopping_cart_add_item",
        args: {
            'component': componentname,
            'itemid': id
        },
        done: function(data) {
           let html = '<li id="item-' + data.itemid + '" class="clearfix" data-price="' + data.price + '" data-name="' + data.name
            + '" data-expirationdate="' + data.expirationdate + '" data-id="' + data.itemid + '">' +
            '<span class="item-name"><i class="fa fa-futbol-o" aria-hidden="true"></i>' + data.itemname + '</span>' +
            '<span class="item-price pull-right">' + data.price + '€</span><br>' +
           '<span class="item-time pl-3">[<span id="time-item-' + data.itemid + '"></span>]</span>' +
            '<span class="pull-right"><i class="fa fa-trash-o lighter-text" data-id="item-' + data.itemid + '"></i></span>' +
            '</li>';
            let lastElem = document.getElementById('litotalprice');
            lastElem.insertAdjacentHTML('beforeBegin', html);
            let itemcount1 = document.getElementById("countbadge");
            let itemcount2 = document.getElementById("itemcount");
            itemcount2.classList.remove("hidden");
            itemcount1.innerHTML++;
            itemcount2.innerHTML++;
            let totalprice = document.getElementById('totalprice');
            totalprice.innerHTML = (parseInt(totalprice.innerHTML) || 0) + parseInt(data.price);
            addDeleteevent(document.querySelector('#item-' + data.itemid + ' .fa-trash-o'));
            setTimer(data.expirationdate, data.itemid);
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log('error', ex);
        }
    }], true);
};



/**
 * Delete Event.
 * @param {*} item
 */
function addDeleteevent(item) {
    item.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        let id = item.dataset.id.split('-').pop();
        deleteItem(id);
    });
}

/**
 * Start the timer.
 * @param {int} duration
 * @param {int} display
 * @param {int} id
 */
function startTimer(duration, display, id) {
    var timer = duration, minutes, seconds;
    setInterval(function () {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);

        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        display.textContent = minutes + ":" + seconds;

        if (--timer < 0) {
            timer = 0;
            deleteItem(id);
        }
    }, 1000);
}

/**
 * Set the timer.
 * @param {int} expirationdate
 * @param {int} id
 */
function setTimer(expirationdate, id) {
    let delta = 0;
    let now = Date.now('UTC');
    now = (new Date()).getTime() / 1000;
    delta = (expirationdate - now);
    if (delta < 0) {
        delta = 0;
    }
    let display = document.querySelector('#time-item-' + id);
    if (display) {
        startTimer(delta, display, id);
    }
}
