<template>
  <div class="login-card">
    <div class="face-icon"></div>
    <h2 class="text-center">Acceso Biométrico</h2>

    <form>

      <div class="mb-3">
        <label for="usuario" class="form-label visually-hidden text-dark">Usuario</label>
        <input type="text" class="form-control text-dark" v-model="emaillo" id="usuario" placeholder="Usuario o ID Facial" required>
      </div>

      <div class="mb-4">
        <div class="input-group-icon">
          <input :type="passwordFieldType" class="form-control clave text-dark" v-model="clave2" placeholder="Contraseña"
            required />

          <span @click="togglePasswordVisibility" class="fa fa-fw field-icon toggle-password text-dark"
            :class="{ 'fa-eye': passwordFieldType === 'password', 'fa-eye-slash': passwordFieldType === 'text' }">
          </span>
        </div>
      </div>

      <div class="d-grid mb-3">
        <button type="submit" class="btn btn-primary btn-lg text-white" v-on:click="login">
           INGRESAR
        </button>
      </div>


    </form>
  </div>
</template>

<script>
import script3 from "@/assets/js/login";
export default {
  mixins: [script3],
  name: 'login',
  methods: {
    togglePasswordVisibility() {
      this.passwordFieldType = this.passwordFieldType === 'password' ? 'text' : 'password'
    }
  },
  data() {
    return {
      clave2: '',
      passwordFieldType: 'password'
    }
  }

}
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style>
@import url("@/assets/styles/login.css");
</style>
