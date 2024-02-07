<!-- // This file is part of Moodle - http://moodle.org/
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

/**
 * Validate if the string does excist.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div class="main-container">
    <notifications width="100%" position="bottom" />
    <div 
      v-if="view == 'cart'" 
      class="icon-container"
    >
      <ShoppingCartBtn />
    </div>
    <div v-else-if="view == 'button'">
      <AddToCartBtn />
    </div>
  </div>
</template>

<script setup>
// Import needed libraries
import { onBeforeRouteUpdate } from 'vue-router';
import { getCurrentInstance } from 'vue';
import { useRouter } from 'vue-router'
import ShoppingCartBtn from '../components/cart/ShoppingCartBtn.vue'
import AddToCartBtn from '../components/addToCart/AddToCartBtn.vue'

// Load Router and Store
const router = useRouter()

const currentInstance = getCurrentInstance()
const view = currentInstance.appContext.config.globalProperties.view
// Checking routes 
const checkRoute = (currentRoute) => {
    if(currentRoute == undefined){
        router.push({ name: 'shopping-cart' });
    }
};

// Trigger the checking route function
onBeforeRouteUpdate((to, from, next) => {
  checkRoute(to);
  next();
});

</script>

<style scoped>

.main-container {
    height: 100%; /* Set the height to 100% */
  }
.icon-container {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
  }
</style>