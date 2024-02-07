// remove from cart
import { notify } from "@kyvg/vue3-notification"

const  removeFromCart = (itemId, store) => {
  
  const removedItem = store.find(obj => obj.id === itemId);
  const updatedStore = store.filter(obj => obj.id !== itemId);

  notify({
    title: 'Item removed from cart',
    text: 'The item ' + removedItem.text + ' was removed from the cart',
    type: 'warning'
  });

  if (Object.keys(updatedStore).length == 0) {
    // stop start timer
    localStorage.setItem('shopping-cart-timer', null);
  }

  return updatedStore;
}

export default removeFromCart;