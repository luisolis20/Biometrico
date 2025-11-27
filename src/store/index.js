import { createStore } from 'vuex'

export default createStore({
  state: {
    role: localStorage.getItem('Rol_bio') || null,
    email: localStorage.getItem('email_bio') || null,
    idusu: localStorage.getItem('id_bio') || null,
    name: localStorage.getItem('name_bio') || null,
    token: localStorage.getItem('token_bio') || null,
    token_type: localStorage.getItem('token_type_bio') || null,
  },
  getters: {
    getIdusu: state => state.idusu,
    isAuthenticated: state => !!state.token,
    getFullToken: state => `${state.token_type} ${state.token}`,
  },
  mutations: {
    setRol_bio(state, nuevoRol) {
      state.role = nuevoRol;
      localStorage.setItem('Rol_bio', nuevoRol);
    },
    setemail_bio(state, nuevoemail) {
      state.email = nuevoemail;
      localStorage.setItem('email_bio', nuevoemail);
    },
    setid_bio(state, nuevoid) {
      state.idusu = nuevoid;
      localStorage.setItem('id_bio', nuevoid);
    },
    setname_bio(state, nuevoname) {
      state.name = nuevoname;
      localStorage.setItem('name_bio', nuevoname);
    },
    settoken_bio(state, token) {
      state.token = token;
      localStorage.setItem('token_bio', token);
    },
    setTokenType_bio(state, type) {
      state.token_type = type;
      localStorage.setItem('token_type_bio', type);
    },
    logout_bio(state) {
      // Limpia el state y localStorage al cerrar sesión
      state.role = null;
      state.email = null;
      state.idusu = null;
      state.name = null;
      state.token = null;
      state.token_type = null;

      localStorage.removeItem('Rol_bio');
      localStorage.removeItem('email_bio');
      localStorage.removeItem('id_bio');
      localStorage.removeItem('name_bio');
      localStorage.removeItem('token_bio');
      localStorage.removeItem('token_type_bio');
      localStorage.removeItem('user_biometrico');
    },
  },
  actions: {},
  modules: {}
})
