import axios from 'axios';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// Same-origin Laravel session + CSRF: axios reads the XSRF-TOKEN cookie
// Laravel sets and sends it as the X-XSRF-TOKEN header on every request.
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

export default axios;
