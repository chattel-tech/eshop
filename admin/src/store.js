import { createStore, applyMiddleware, compose } from 'redux';
import rootReducer from './reducers';
import thunk from 'redux-thunk';
import { loadTokenState, loadLoggedInState, 
    loadUserState, loadTokenExpiredState, saveTokenState, 
    saveLoggedInState, saveUserState, saveTokenExpiredState } from './localStorage';
import throttle from 'lodash/throttle';

const middleware = [thunk];

let persistedState = {
   auth: {
        token: loadTokenState(),
        user: loadUserState(),
        isLoggedIn: loadLoggedInState(),
        isLoading: false,
        loginFailed: false
    }
} 

const store = createStore(rootReducer, persistedState,
    compose(applyMiddleware(...middleware),  window.__REDUX_DEVTOOLS_EXTENSION__ && window.__REDUX_DEVTOOLS_EXTENSION__()));

store.subscribe(throttle(() => {
    saveTokenState(store.getState().auth.token);
    saveLoggedInState(store.getState().auth.isLoggedIn);
    saveUserState(store.getState().auth.user);
}, 1000));

export default store;