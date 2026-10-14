// Namespaces for this language; add a file here and to the other language.
import { flatten } from '../../flatten';
import account from './account';
import admin from './admin';
import auth from './auth';
import buyer from './buyer';
import common from './common';
import discover from './discover';
import help from './help';
import home from './home';
import layout from './layout';
import location from './location';
import materials from './materials';
import nav from './nav';
import orderDoc from './orderDoc';
import orders from './orders';
import photos from './photos';
import product from './product';
import proposals from './proposals';
import search from './search';
import status from './status';
import supplier from './supplier';
import twoFactor from './twoFactor';

export default flatten({ account, admin, auth, buyer, common, discover, help, home, layout, location, materials, nav, orderDoc, orders, photos, product, proposals, search, status, supplier, twoFactor });
