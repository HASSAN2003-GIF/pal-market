// Namespaces for this language; add a file here and to the other language.
import { flatten } from '../../flatten';
import account from './account';
import admin from './admin';
import auth from './auth';
import buyer from './buyer';
import common from './common';
import help from './help';
import home from './home';
import layout from './layout';
import location from './location';
import materials from './materials';
import nav from './nav';
import orders from './orders';
import product from './product';
import search from './search';
import status from './status';
import supplier from './supplier';

export default flatten({ account, admin, auth, buyer, common, help, home, layout, location, materials, nav, orders, product, search, status, supplier });
