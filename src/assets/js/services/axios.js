import axios from "axios";

const API = axios.create({
  baseURL: `${__API_BIO__}`,
  timeout: 90000,
});

API.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem("token_cvn");
    const tokenType = localStorage.getItem("token_type_cvn");

    if (token && tokenType) {
      config.headers.Authorization = `${tokenType} ${token}`;
    }

    return config;
  },
  (error) => {
    console.error("❌ Error en la solicitud:", error);
    return Promise.reject(error);
  }
);

API.interceptors.response.use(
  (response) => response, // deja pasar las respuestas correctas
  (error) => {
    if (error.response) {
      console.warn("⚠️ Error en respuesta del servidor:", {
        status: error.response.status,
        data: error.response.data,
        url: error.config.url,
      });

      // Puedes manejar ciertos códigos de error
      if (error.response.status === 401) {
        console.warn("🔒 Sesión expirada. Cerrando sesión...");
        localStorage.removeItem("token_cvn");
        localStorage.removeItem("token_type_cvn");
        window.location.href = "/biometricoutlvte"; // Redirige al login si la sesión caduca
      }
    } else if (error.request) {
      console.error("📡 No hubo respuesta del servidor:", error.request);
    } else {
      console.error("⚙️ Error al configurar la solicitud:", error.message);
    }

    return Promise.reject(error);
  }
);
export default API