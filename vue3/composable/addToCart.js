// add to cart
import { notify } from "@kyvg/vue3-notification"

const  addToCart = (item, store) => {
  if (Object.keys(store).length == 0) {
    // set start timer
    localStorage.setItem('shopping-cart-timer', Date.now().toString());
  }
  if (Object.keys(store).length >= 4) {
    notify({
      title: 'Shopping cart is full',
      text: 'The shopping cart is already full!',
      type: 'warning'
    });
    return store
  }
  const isUnique = !store.some(obj => obj.id === item.id);
  if (isUnique) {
    // The array doesn't contain an object with the same ID, so you can proceed
    store.push(item);
    notify({
      title: 'Added to cart',
      text: 'Item was added to shopping cart',
      type: 'success'
    });
  } else {
    notify({
      title: 'Item already in cart',
      text: 'The item ' + item.text + ' is already inside the cart',
      type: 'info'
    });
  }
  return store
}

export default addToCart;