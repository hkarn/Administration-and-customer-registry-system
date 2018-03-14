import {myAxios} from '../config/axios';
import {errorPopup} from './error-popup';
import {getToken} from './get-token';

export function Login(logindata) {
  
  const errprep = logindata.auto ? 'Automatisk inlogging misslyckades! ' : '';
  return async (dispatch) => {
    dispatch({type: 'LOADING', payload: true});
    try {
      let token = await getToken('login');
      let response = await myAxios.post( '/auth', {
        user: logindata.user,
        pwd: logindata.pwd,
        logintoken: token.data.logintoken
      });
      let payload = {
        login: false,
        autoAttempt: false,
      };         
      try {
        if (response.data.login !== undefined) {
          payload = {...payload, ...response.data};
        }
      } catch(e) {
        dispatch(errorPopup({visible: true, message: errprep + 'Okänt svar från API.'}));
      }
      dispatch({type: 'LOGIN', payload: payload});
      dispatch({type: 'LOADING', payload: false});
    } catch (error) {
      let errormsg = errprep + 'Ett fel har uppstått i inloggningen. ';
      try {
        if (error.response.data.response !== undefined) {
          errormsg = errprep + error.response.data.response;
        }
      } catch(e) {
        errormsg = errprep + 'Felformaterat eller inget svar från API.';
      }
      dispatch(errorPopup({visible: true, message: errormsg}));
      dispatch({type: 'LOGIN', payload: {autoAttempt: false}});
      dispatch({type: 'LOADING', payload: false});
      throw error;
    }
  };

}