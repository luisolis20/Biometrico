import { mostraralertas } from "@/assets/js/function/funciones";
import { enviarsolilogin } from "@/assets/js/function/login_function";
import store from "@/store";
import { getMe } from '@/store/auth';
export default {
  data() {
    return {
      emaillo: "",
      clave2: "",
      url2: `${__API_BIO__}/biometrico/login`,
    };
  },
  methods: {
    async login(event) {
      event.preventDefault();
      try {
        var parametros = {
          CIInfPer: this.emaillo.trim(),
          codigo_dactilar: this.clave2.trim(),
        };

        const response = await enviarsolilogin('POST', parametros, this.url2, 'Logueado');

        if (response.error) {
          mostraralertas(response.mensaje, 'warning');
        } else if (response) {
          //  getMe() justo después de guardar el token
          const usuario = await getMe(); // Esto obtiene los datos del usuario autenticado desde /auth/me
          //console.log("Usuario autenticado:", usuario);

          // Redirección según el rol
          const role = response.Rol;
          const tok = response.token;
          //console.log(response.id);
          //console.log(response);
          if (role === 'Administrador') {
            mostraralertas('LE DAMOS LA BIENVENIDA ADMIN ' + (response.name || ''), 'success');
            this.$router.push('/adminus/' + response.id);
          } else if (role === 'Estudiante') {
            mostraralertas('LE DAMOS LA BIENVENIDA ESTUDIANTE ' + (response.ApellInfPer || ''), 'success');
            this.$router.push('/user/' + response.CIInfPer);

          } else if (role === 'Estudiante Graduado') {
            mostraralertas('LE DAMOS LA BIENVENIDA ESTUDIANTE GRADUADO ' + (response.ApellInfPer || ''), 'success');
            this.$router.push('/user/' + response.CIInfPer);
          }
          else if (role === 'Docente') {
            mostraralertas('LE DAMOS LA BIENVENIDA DOCENTE ' + (response.ApellInfPer || ''), 'success');
            this.$router.push('/userdocente/' + response.CIInfPer);
          }
        }
      } catch (error) {
        console.error("Error en login:", error);
        if (error.response?.data?.mensaje) {
          mostraralertas(error.response.data.mensaje, 'warning');
        } else {
          mostraralertas('No se pudo conectar con el servidor o error inesperado.', 'error');
        }
      }
    },
  },
};
