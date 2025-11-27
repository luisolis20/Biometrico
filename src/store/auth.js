//Codigo para la autenticación del usuario
import { ref } from 'vue';// Librería para poder usar el ref
import axios from 'axios'; //Librería para poder usar el axios
import store from "@/store";
// Importación de la librería para poder usar el ref
const logged = ref(false); // Variable para almacenar si el usuario está logueado o no
const user = ref('');// Variable para almacenar el usuario logueado
const meURL = `${__API_BIO__}/biometrico/me`; // URL para el endpoint de login

// Creación de un cliente de axios
const apiClient = axios.create({
  baseURL: meURL,
  headers: {
    'Content-Type': 'application/json',
  },
});
// Interceptor para agregar el token al header de la petición
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token_bio');
    const tokenType = localStorage.getItem('token_type_bio');
    if (token && tokenType) {
      config.headers.Authorization = `${tokenType} ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);
// Función para obtener el usuario logueado
export const getMe = async () => {
  try {
    const response = await apiClient.get('');
    localStorage.setItem('user_biometrico', JSON.stringify(response.data));
    logged.value = true;
    user.value = response.data;
    //console.log(response.data);
    return response.data;
  } catch (error) {
    localStorage.clear();
    window.location.href = '/biometricoutlvte';
    console.error('Error al obtener perfil data:', error);
    throw error;
  }
};
