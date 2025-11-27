<template>
  <div id="spinner"
    class="w-100 vh-100 bg-white position-fixed translate-middle top-50 start-50 d-flex align-items-center justify-content-center"
    style="z-index: 9999;">
    <div class="spinner-grow text-primary" role="status"></div>
  </div>
  
  <div class="verde container-fluid">
    <div v-if="showNavbarNue" class="container-xxl container-fluid position-relative bg-white d-flex">
      
      <div class="sidebar pe-8 pb-8" v-if="mostrarOpciones">
        <nav class="navbar">
          <a href="" class="navbar-brand mx-4 mb-4">
            <img class="imagen" src="" alt="">
          </a>
          <div class="d-flex align-items-center ms-4 mb-4">
            <div class="position-relative">
              <img class="rounded-circle" src="" alt="" style="width: 40px; height: 40px;">
              <div class="bg-success rounded-circle border border-2 border-white position-absolute end-0 bottom-0 p-1">
              </div>
            </div>
            <div class="ms-3">
              <h6 class="mb-0">{{ names }}</h6>
              <span class="text-dark">{{ roles }}</span>
            </div>
          </div>
          <div class="navbar-nav w-100 oe">
            <router-link :to="{ path: '/adminus/' + idus }" class="nav-item nav-link"><i class="fas fa-home me-2"
                :class="{ 'active': $route.path === '/adminus/' + idus }"></i>Bienvenido</router-link>
            <router-link :to="{ path: '/userall/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/userall/' + idus }"><i
                class="fa fa-user me-2"></i>Usuarios</router-link>
            <router-link :to="{ path: '/datos_personales_adm/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/datos_personales_adm/' + idus }"><i
                class="fas fa-database me-2"></i>CVN Registrados.</router-link>
            <router-link :to="{ path: '/declaracion_personal_adm/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/declaracion_personal_adm/' + idus }"><i
                class="fas fa-person-booth me-2"></i>Declaraciones Per.</router-link>
            <router-link :to="{ path: '/experiencia_profesionales_adm/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/experiencia_profesionales_adm/' + idus }"><i
                class="fas fa-briefcase me-2"></i>Experiencias Pro.</router-link>
            <router-link :to="{ path: '/formacion_academica_adm/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/formacion_academica_adm/' + idus }"><i
                class="fas fa-book-reader me-2"></i>Formación Aca.</router-link>
            <router-link :to="{ path: '/habilidades_informatica_adm/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/habilidades_informatica_adm/' + idus }"><i
                class="fas fa-desktop me-2"></i>Habilidades Infor.</router-link>
            <router-link :to="{ path: '/idioma_adm/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/idioma_adm/' + idus }"><i
                class="fa fa-commenting me-2"></i>Idiomas</router-link>
            <router-link :to="{ path: '/informacion_contacto_adm/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/informacion_contacto_adm/' + idus }"><i
                class="fas fa-address-card me-2"></i>Información Cont.</router-link>
            <router-link :to="{ path: '/investigacion_publicaciones_adm/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/investigacion_publicaciones_adm/' + idus }"><i
                class="fas fa-glasses me-2"></i>Inves. / Publi.</router-link>
            <router-link :to="{ path: '/otros_datos_adm/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/otros_datos_adm/' + idus }"><i class="fas fa-coins me-2"></i>Otros
              Datos</router-link>
            <router-link :to="{ path: '/cursos_capacitacion_adm/' + idus }" class="nav-item nav-link"
              :class="{ 'active': $route.path === '/cursos_capacitacion_adm/' + idus }"><i
                class="fas fa-bezier-curve"></i>Cursos</router-link>

          </div>
        </nav>
      </div>
      <div class="content container-fluid">
        <div class="container-fluid topbar bg-primary py-2 px-3">
          <div class="d-flex flex-wrap justify-content-between align-items-center text-white">
            <div class="top-info mb-2 mb-lg-0 text-center text-lg-start">
              <small class="d-block d-lg-inline me-lg-3">
                <i class="fas fa-map-marker-alt me-2 text-secondary"></i>
                <a href="#" class="text-white text-decoration-none">Bienestar Universiversitario, Nuevos Horizontes,
                  Esmeraldas, Ecuador</a>
              </small>
              <small class="d-block d-lg-inline">
                <i class="fas fa-envelope me-2 text-secondary"></i>
                <a href="#" class="text-white text-decoration-none">bienestaruniversitario@utelvt.edu.ec</a>
              </small>
            </div>
            </div>
        </div>
        <nav class="navbar navbar-expand navbar-light sticky-top px-4 py-0 align-items-center">

          <a href="#" class="sidebar-toggler flex-shrink-0" v-on:click="ocultar">
            <i class="fa fa-bars"></i>
          </a>
          <h2 class="paneladmin">Panel de Administración</h2>

          <div class="navbar-nav align-items-center ms-auto">
            <div class="nav-item dropdown">
              <a href="#" class="nav-link dropdown-toggle" role="button" data-bs-toggle="dropdown"
                aria-expanded="false">
                <img class="rounded-circle me-lg-2" src="" alt="" style="width: 40px; height: 40px;">
                <span class="d-none d-lg-inline-flex">{{ names }}</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end bg-light border-0 rounded-0 rounded-bottom m-0">
                <li>
                  <a href="#" class="dropdown-item" @click="cerrarsesion">Cerrar Sesión</a>
                </li>
              </ul>
            </div>
          </div>

        </nav>
        <div class="mt-3">
          <router-view></router-view>
        </div>
      </div>
    </div>
    
    <router-view v-else></router-view>
  </div>
</template>

<style scoped>
@import url('@/assets/styles/main.css');
@import url('@/assets/styles/style.css');
</style>
<script>
//Importar Librerías y Archivos Js
import script2 from '@/assets/js/custom.js';
import { mapActions } from 'vuex';
import store from '@/store';
import API from "@/assets/js/services/axios.js";

export default {
  mounted() {

  },
  //Creción de Métodos
  methods: {
    //Método para cerrar Sesión
    async cerrarsesion() {
      try {
        const token = localStorage.getItem("token_cvn");

        if (!token) {
          console.warn("⚠️ No hay token, cerrando sesión localmente...");
          localStorage.clear();
          window.location.href = "/cvn/home";
          return;
        }

        const response = await API.get(
          "/cvn/logout",
          {},
          {
            headers: {
              Authorization: `Bearer ${token}`,
            },
          }
        );

        console.log("✅ Sesión cerrada:", response.data);

        // Limpia todo lo del localStorage
        localStorage.clear();

        // Redirige al login
        window.location.href = "/cvn/home";
      } catch (error) {
        console.error("❌ Error al cerrar sesión:", error.response?.data || error);
        // Si el token no es válido o expiró, limpiar igual
        localStorage.clear();
        window.location.href = "/cvn/home";
      }
    },
    //Método para Ocultar el menu del Archivo Administrador.vue
    ocultar() {
      // Sidebar Toggler
      $('.sidebar, .content').toggleClass("open");
      return false;

    },
    toggleNavbarCollapse() {
      const navbarCollapse = document.getElementById('navbarCollapse');
      const toggler = this.$refs.navbarToggler; // Usaremos una referencia ref

      if (navbarCollapse.classList.contains('show')) {
        // Si está abierto, lo cierra (añade 'collapse' y quita 'show')
        navbarCollapse.classList.remove('show');
        toggler.setAttribute('aria-expanded', 'false');
        // Opcional: añade 'collapsing' para la animación, luego 'collapse'
        // Por simplicidad, solo manejamos show/collapse
        navbarCollapse.classList.add('collapse');
      } else {
        // Si está cerrado, lo abre (quita 'collapse' y añade 'show')
        navbarCollapse.classList.add('show');
        navbarCollapse.classList.remove('collapse');
        toggler.setAttribute('aria-expanded', 'true');
      }
    },
  },
  mixins: [script2],//Habilitar el uso del archivos js
};
</script>
