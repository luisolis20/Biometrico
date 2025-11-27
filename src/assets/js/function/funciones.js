import Swal from 'sweetalert2';
import API from '@/assets/js/services/axios';

export function mostraralertas(titulo,icono,foco=''){
    if(foco!=''){
        document.getElementById(foco).focus();
    }
    Swal.fire({
        title:titulo,
        icon:icono,
        customClass:{confirmButton:'btn btn-secondary', popup:'animated zoonIn'},
        buttonsStyling:false
    });
}
export function mostraralertas2(titulo,icono){
    
    Swal.fire({
        title:titulo,
        icon:icono,
        customClass:{confirmButton:'btn btn-secondary', popup:'animated zoonIn'},
        buttonsStyling:false
    });
}
export function confimar(urlconslash,id,titulo,mensaje){
    var url = urlconslash+id;
    const swalwithboostrapbutton = Swal.mixin({
        customClass:{confirmButton:'btn btn-success me-3',cancelButton:'btn btn-danger'},
    });
    return swalwithboostrapbutton.fire({
        title:titulo,
        text:mensaje,
        icon:'question',
        showCancelButton:true,
        confirmButtonText:'<i class="fa-solid fa-check"></i> Si, Eliminar',
        cancelButtonText:'<i class="fa-solid fa-ban"></i> Cancelar'}).then((res)=>{
        if(res.isConfirmed){
            return enviarsolig('DELETE',{id:id},url,'Eliminado con exito').then(response => {
                return response; 
            });
        }else{
            mostraralertas('Operacion cancelada','info');
            return null;
        }
    });
   
}
export function confimar2(urlconslash,id,titulo,mensaje){
    var url = urlconslash+id;
    const swalwithboostrapbutton = Swal.mixin({
        customClass:{confirmButton:'btn btn-success me-3',cancelButton:'btn btn-danger'},
    });
    swalwithboostrapbutton.fire({
        title:titulo,
        text:mensaje,
        icon:'question',
        showCancelButton:true,
        confirmButtonText:'<i class="fa-solid fa-check"></i> Si, Eliminar',
        cancelButtonText:'<i class="fa-solid fa-ban"></i> Cancelar'}).then((res)=>{
        if(res.isConfirmed){
            enviarsolig('PUT',{id:id},url,'Deshabilitado con éxito');
        }else{
            mostraralertas('Operacion cancelada','info');
        }
    });
   
}
export function enviarsoli(metodo,parametros,url,mensaje){
    API({
        method:metodo,
        url:url,
        data:parametros
    }).then(function(res){
        var estado = res.status;
        if(estado==200){
            mostraralertas(mensaje,'success');
            document.getElementById('IniciarSesion').scrollIntoView({ behavior: 'smooth' });
            /*window.setTimeout(function(){
                window.location.href='/'
            },2000);*/
        }else{
            mostraralertas('No se pudo recuperar la respuesta','error');
        }
    }).catch(function(error){
        mostraralertas('Error Al registrar','error');
    });
}
export function enviarsolig(metodo,parametros,url,mensaje){
    return API({
        method:metodo,
        url:url,
        data:parametros
    }).then(function(res){
        var estado = res.status;
        if(estado==200){
            mostraralertas(mensaje,'success');
            return res;   
        }else{
            mostraralertas('No se pudo recuperar la respuesta','error');

        }
    }).catch(function(error){
        console.log(error);
        mostraralertas('Servidor no Disponible','error');
    });
}
export function enviarsoligfoot(metodo,parametros,url,mensaje){
    return API({
        method:metodo,
        url:url,
        data:parametros
    }).then(function(res){
        var estado = res.status;
        if(estado==200){
            mostraralertas(mensaje,'success');
            return res;   
        }else{
            mostraralertas('No se pudo recuperar la respuesta','error');

        }
    }).catch(function(error){
        console.log(error);
        mostraralertas('Servidor no Disponible','error');
    });
}
export function enviarsoligqr(metodo,parametros,url){
    return API({
        method:metodo,
        url:url,
        data:parametros
    }).then(function(res){
        var estado = res.status;
        if(estado==200){
            return res;   
        }else{
            console.log('No se pudo recuperar la respuesta','error');

        }
    }).catch(function(error){
        console.log(error);
    });
}
export async function enviarsoliedit(metodo,parametros,url,mensaje){
    try {
        var response = await API({
        method: metodo,
        url: url,
        data: parametros
      });
  
      
      if (response.data) {
        //console.log(mensaje + ': ' + response.data.mensaje);
        mostraralertas(mensaje,'success');
        
        
        
      } else{
        mostraralertas('No se pudo recuperar la respuesta','error');
        return null;
        }
    } catch (error) {
      console.error('Error:', error.response.data);
      mostraralertas('Servidor no Disponible','error');
      throw error;
    }
}