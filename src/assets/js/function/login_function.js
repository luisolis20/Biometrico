//Funcion para el login del usuario
import axios from "axios";
import store from "@/store";
export async function enviarsolilogin(method, parametros, url, mensaje) {
  try {
    const response = await axios({
      method: method,
      url: url,
      data: parametros,
    });

    if (response.data && response.data.token) {
      if (response.data.error) {
        return {
          error: response.data.error,
          clave: response.data.clave,
          mensaje: response.data.mensaje,
        };
      } else if (response.data.Rol === "Administrador") {
        store.commit("setRol_bio", response.data.Rol);
        store.commit("setemail_bio", response.data.email);
        store.commit("setid_bio", response.data.id);

        store.commit("setname_bio", response.data.name);
        store.commit("settoken_bio", response.data.token);
        store.commit("setTokenType_bio", response.data.token_type || "Bearer");
        return {
          token: response.data.token,
          Rol: response.data.Rol,
          id: response.data.id,
          name: response.data.name,
          email: response.data.email,
        };
      }
      else if(response.data.Rol === "SuperAdministrador") {
        store.commit("setRol_bio", response.data.Rol);
        store.commit("setemail_bio", response.data.email);
        store.commit("setid_bio", response.data.id);

        store.commit("setname_bio", response.data.name);
        store.commit("settoken_bio", response.data.token);
        store.commit("setTokenType_bio", response.data.token_type || "Bearer");
        return {
          token: response.data.token,
          Rol: response.data.Rol,
          id: response.data.id,
          name: response.data.name,
          email: response.data.email,
        };
      }
    } else {
      console.error("Respuesta inesperada:", response);
      return null;
    }
  } catch (error) {
    console.error("Error:", error.response.data);
    throw error;
  }
}
