import $ from "jquery";
import store from "@/store";

export default {
  data() {
    return {
      idus: "",
      roles: "",
      emaile: "",
      names: "",
      passwordFieldType: 'password',
      // ruta base de tu PDF
      pdfUrl: `${process.env.BASE_URL}Docs/Manual_CVN__V1.pdf`,
      // página inicial (se reemplaza al llamar al modal)
      pdfPage: 1
    };
  },
  
  mounted() {
    
    
  },
  computed: {
    showNavbar() {
      // Lógica para determinar si mostrar o no el navbar
      return this.$route.name == "login";
    },
    pdfSrc() {
      return `${this.pdfUrl}#page=${this.pdfPage}`;
    },

    rolUsuario() {
      //console.log(store);
      return store.state.role;
    },
    emailUsuario() {
      //console.log(store);
      return store.state.email;
    },
    idUsuario() {
      //console.log(store);
      return store.state.idusu;
    },
    nombreUsuario() {
      //console.log(store);
      return store.state.name;
    },
    showNavbarNue() {

      var rut;
      rut = this.$route.name;
      return (
        (this.$route.name !== "login") & (this.rolUsuario === "Administrador")
      );
    },
    
    mostrarOpciones() {
      //console.log(this.rolUsuario);
      this.roles = this.rolUsuario;
      this.emaile = this.emailUsuario;
      this.idus = this.idUsuario;
      this.names = this.nombreUsuario;
      return this.rolUsuario === "Administrador";
    },
    
  },
  methods: {
    openPdfModal(page) {
      this.pdfPage = page;
      const modalEl = this.$refs.pdfModal;
      const modal = new bootstrap.Modal(modalEl);
      modal.show();
    },
    closePdfModal() {
      const modalEl = this.$refs.pdfModal;
      const modal = bootstrap.Modal.getInstance(modalEl);
      modal.hide();
    }
  },
};
