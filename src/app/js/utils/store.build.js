/* eslint-disable no-param-reassign */
/* eslint indent: ["error", "tab", { "SwitchCase": 1 }] */
/* eslint-disable no-new */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint no-undef: "error" */

import engine from 'store/src/store-engine';
import sessionStorage from 'store/storages/sessionStorage';
import defaultPlugin from 'store/plugins/defaults';
import expiredPlugin from 'store/plugins/expire';
import eventsPlugin from 'store/plugins/events';

const storages = [sessionStorage];
const plugins = [defaultPlugin, expiredPlugin, eventsPlugin];
const sessionStore = engine.createStore(storages, plugins);

export default sessionStore;
