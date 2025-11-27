<template>
    hola
</template>
<style>
@import url('@/assets/styles/style.css');
</style>
<script>
import script2 from '@/assets/js/custom.js';
import axios from 'axios';
import { useRoute } from 'vue-router';
import API from '@/assets/js/services/axios';
export default {
    data() {
        return {
            idus: 0,
            url255: '/biometrico/users',
            usuarios: null,
            cargando: false
        }
    },
    mounted() {
        const ruta = useRoute();
        this.idus = ruta.params.id;
        this.getUsuarios();
        
        
    },

    methods: {
        getUsuarios() {
            this.cargando = true;
            API.get(this.url255).then(
                res => {
                    this.usuarios = res.data;
                    this.cargando = false;
                }

            );
        },
        eliminar(id, nombre) {
            confimar('http://backendbolsaempleo.test/api/v1/empresas/', id, 'Eliminar registro', '¿Realmente desea eliminar a ' + nombre + '?');
            this.cargando = false;
            this.$router.push('/principal/' + this.idus);

        }

    },
    mixins: [script2],
    name:'admin',
};
</script>