/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, {
/******/ 				configurable: false,
/******/ 				enumerable: true,
/******/ 				get: getter
/******/ 			});
/******/ 		}
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "";
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 1);
/******/ })
/************************************************************************/
/******/ ([
/* 0 */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


Object.defineProperty(exports, "__esModule", {
  value: true
});
/**
 * Contains the registered hooks, keyed by hook type. Each hook type is an
 * array of objects with priority and callback of each registered hook.
 */
var HOOKS = {
  actions: {},
  filters: {}
};

exports.default = HOOKS;

/***/ }),
/* 1 */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


var _hooks = __webpack_require__(0);

var _hooks2 = _interopRequireDefault(_hooks);

var _ = __webpack_require__(2);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

var hooks = {
	addAction: _.addAction,
	addFilter: _.addFilter,
	removeAction: _.removeAction,
	removeFilter: _.removeFilter,
	removeAllActions: _.removeAllActions,
	removeAllFilters: _.removeAllFilters,
	hasAction: _.hasAction,
	hasFilter: _.hasFilter,
	doAction: _.doAction,
	applyFilters: _.applyFilters,
	currentAction: _.currentAction,
	currentFilter: _.currentFilter,
	doingAction: _.doingAction,
	doingFilter: _.doingFilter,
	didAction: _.didAction,
	didFilter: _.didFilter
};

window.wp = window.wp || {};
window.wp.hooks = hooks;

/***/ }),
/* 2 */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.didFilter = exports.didAction = exports.doingFilter = exports.doingAction = exports.currentFilter = exports.currentAction = exports.applyFilters = exports.doAction = exports.removeAllFilters = exports.removeAllActions = exports.hasFilter = exports.hasAction = exports.removeFilter = exports.removeAction = exports.addFilter = exports.addAction = undefined;

var _hooks = __webpack_require__(0);

var _hooks2 = _interopRequireDefault(_hooks);

var _createAddHook = __webpack_require__(3);

var _createAddHook2 = _interopRequireDefault(_createAddHook);

var _createRemoveHook = __webpack_require__(4);

var _createRemoveHook2 = _interopRequireDefault(_createRemoveHook);

var _createHasHook = __webpack_require__(5);

var _createHasHook2 = _interopRequireDefault(_createHasHook);

var _createRunHook = __webpack_require__(6);

var _createRunHook2 = _interopRequireDefault(_createRunHook);

var _createDoingHook = __webpack_require__(7);

var _createDoingHook2 = _interopRequireDefault(_createDoingHook);

var _createDidHook = __webpack_require__(8);

var _createDidHook2 = _interopRequireDefault(_createDidHook);

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

// Add action/filter functions.
var addAction = exports.addAction = (0, _createAddHook2.default)(_hooks2.default.actions);
var addFilter = exports.addFilter = (0, _createAddHook2.default)(_hooks2.default.filters);

// Remove action/filter functions.
var removeAction = exports.removeAction = (0, _createRemoveHook2.default)(_hooks2.default.actions);
var removeFilter = exports.removeFilter = (0, _createRemoveHook2.default)(_hooks2.default.filters);

// Has action/filter functions.
var hasAction = exports.hasAction = (0, _createHasHook2.default)(_hooks2.default.actions);
var hasFilter = exports.hasFilter = (0, _createHasHook2.default)(_hooks2.default.filters);

// Remove all actions/filters functions.
var removeAllActions = exports.removeAllActions = (0, _createRemoveHook2.default)(_hooks2.default.actions, true);
var removeAllFilters = exports.removeAllFilters = (0, _createRemoveHook2.default)(_hooks2.default.filters, true);

// Do action/apply filters functions.
var doAction = exports.doAction = (0, _createRunHook2.default)(_hooks2.default.actions);
var applyFilters = exports.applyFilters = (0, _createRunHook2.default)(_hooks2.default.filters, true);

// Current action/filter functions.
var currentAction = exports.currentAction = function currentAction() {
  return _hooks2.default.actions.current || null;
};
var currentFilter = exports.currentFilter = function currentFilter() {
  return _hooks2.default.filters.current || null;
};

// Doing action/filter: true while a hook is being run.
var doingAction = exports.doingAction = (0, _createDoingHook2.default)(_hooks2.default.actions);
var doingFilter = exports.doingFilter = (0, _createDoingHook2.default)(_hooks2.default.filters);

// Did action/filter functions.
var didAction = exports.didAction = (0, _createDidHook2.default)(_hooks2.default.actions);
var didFilter = exports.didFilter = (0, _createDidHook2.default)(_hooks2.default.filters);

/***/ }),
/* 3 */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


Object.defineProperty(exports, "__esModule", {
	value: true
});
/**
 * Returns a function which, when invoked, will add a hook.
 *
 * @param  {Object}   hooks Stored hooks, keyed by hook name.
 *
 * @return {Function}       Function that adds a new hook.
 */
function createAddHook(hooks) {
	/**
  * Adds the hook to the appropriate hooks container.
  *
  * @param {string}   hookName Name of hook to add
  * @param {Function} callback Function to call when the hook is run
  * @param {?number}  priority Priority of this hook (default=10)
  */
	return function addHook(hookName, callback, priority) {
		if (typeof hookName !== 'string') {
			console.error('The hook name must be a string.');
			return;
		}

		if (typeof callback !== 'function') {
			console.error('The hook callback must be a function.');
			return;
		}

		// Assign default priority
		if ('undefined' === typeof priority) {
			priority = 10;
		} else {
			priority = parseInt(priority, 10);
		}

		// Validate numeric priority
		if (isNaN(priority)) {
			console.error('The hook priority must be omitted or a number.');
			return;
		}

		var handler = { callback: callback, priority: priority };
		var handlers = void 0;

		if (hooks.hasOwnProperty(hookName)) {
			// Find the correct insert index of the new hook.
			handlers = hooks[hookName];
			var i = 0;
			while (i < handlers.length) {
				if (handlers[i].priority > priority) {
					break;
				}
				i++;
			}
			// Insert (or append) the new hook.
			handlers.splice(i, 0, handler);
		} else {
			// This is the first hook of its type.
			handlers = [handler];
		}

		hooks[hookName] = handlers;
	};
}

exports.default = createAddHook;

/***/ }),
/* 4 */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


Object.defineProperty(exports, "__esModule", {
	value: true
});
/**
 * Returns a function which, when invoked, will remove a specified hook or all
 * hooks by the given name.
 *
 * @param  {Object}   hooks      Stored hooks, keyed by hook name.
 * @param  {bool}     removeAll  Whether to remove all hooked callbacks.
 *
 * @return {Function}            Function that removes hooks.
 */
function createRemoveHook(hooks, removeAll) {
	/**
  * Removes the specified callback (or all callbacks) from the hook with a
  * given name.
  *
  * @param {string}    hookName The name of the hook to modify.
  * @param {?Function} callback The specific callback to be removed.  If
  *                             omitted (and `removeAll` is truthy), clears
  *                             all callbacks.
  */
	return function removeHook(hookName, callback) {
		// Bail if no hooks exist by this name
		if (!hooks.hasOwnProperty(hookName)) {
			return;
		}

		if (removeAll) {
			var runs = hooks[hookName].runs;
			hooks[hookName] = [];
			if (runs) {
				hooks[hookName].runs = runs;
			}
		} else if (callback) {
			// Try to find the specified callback to remove.
			var handlers = hooks[hookName];
			for (var i = handlers.length - 1; i >= 0; i--) {
				if (handlers[i].callback === callback) {
					handlers.splice(i, 1);
				}
			}
		}
	};
}

exports.default = createRemoveHook;

/***/ }),
/* 5 */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


Object.defineProperty(exports, "__esModule", {
	value: true
});
/**
 * Returns a function which, when invoked, will return whether any handlers are
 * attached to a particular hook.
 *
 * @param  {Object}   hooks Stored hooks, keyed by hook name.
 *
 * @return {Function}       Function that returns whether any handlers are
 *                          attached to a particular hook.
 */
function createHasHook(hooks) {
	/**
  * Returns how many handlers are attached for the given hook.
  *
  * @param  {string}  hookName The name of the hook to check for.
  *
  * @return {number}           The number of handlers that are attached to
  *                            the given hook.
  */
	return function hasHook(hookName) {
		return hooks.hasOwnProperty(hookName) ? hooks[hookName].length : 0;
	};
}

exports.default = createHasHook;

/***/ }),
/* 6 */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


Object.defineProperty(exports, "__esModule", {
	value: true
});
/**
 * Returns a function which, when invoked, will execute all callbacks
 * registered to a hook of the specified type, optionally returning the final
 * value of the call chain.
 *
 * @param  {Object}   hooks          Stored hooks, keyed by hook name.
 * @param  {?bool}    returnFirstArg Whether each hook callback is expected to
 *                                   return its first argument.
 *
 * @return {Function}                Function that runs hook callbacks.
 */
function createRunHook(hooks, returnFirstArg) {
	/**
  * Runs all callbacks for the specified hook.
  *
  * @param  {string} hookName The name of the hook to run.
  * @param  {...*}   args     Arguments to pass to the hook callbacks.
  *
  * @return {*}               Return value of runner, if applicable.
  */
	return function runHooks(hookName) {
		for (var _len = arguments.length, args = Array(_len > 1 ? _len - 1 : 0), _key = 1; _key < _len; _key++) {
			args[_key - 1] = arguments[_key];
		}

		var handlers = hooks[hookName];
		var maybeReturnValue = args[0];

		if (!handlers) {
			return returnFirstArg ? maybeReturnValue : undefined;
		}

		hooks.current = hookName;
		handlers.runs = (handlers.runs || 0) + 1;

		handlers.forEach(function (handler) {
			maybeReturnValue = handler.callback.apply(null, args);
			if (returnFirstArg) {
				args[0] = maybeReturnValue;
			}
		});

		delete hooks.current;

		if (returnFirstArg) {
			return maybeReturnValue;
		}
	};
}

exports.default = createRunHook;

/***/ }),
/* 7 */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


Object.defineProperty(exports, "__esModule", {
	value: true
});
/**
 * Returns a function which, when invoked, will return whether a hook is
 * currently being executed.
 *
 * @param  {Object}   hooks Stored hooks, keyed by hook name.
 *
 * @return {Function}       Function that returns whether a hook is currently
 *                          being executed.
 */
function createDoingHook(hooks) {
	/**
  * Returns whether a hook is currently being executed.
  *
  * @param  {?string} hookName The name of the hook to check for.  If
  *                            omitted, will check for any hook being executed.
  *
  * @return {bool}             Whether the hook is being executed.
  */
	return function doingHook(hookName) {
		// If the hookName was not passed, check for any current hook.
		if ('undefined' === typeof hookName) {
			return 'undefined' !== typeof hooks.current;
		}

		// Return the current hook.
		return hooks.current ? hookName === hooks.current : false;
	};
}

exports.default = createDoingHook;

/***/ }),
/* 8 */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


Object.defineProperty(exports, "__esModule", {
	value: true
});
/**
 * Returns a function which, when invoked, will return the number of times a
 * hook has been called.
 *
 * @param  {Object}   hooks Stored hooks, keyed by hook name.
 *
 * @return {Function}       Function that returns a hook's call count.
 */
function createDidHook(hooks) {
	/**
  * Returns the number of times an action has been fired.
  *
  * @param  {string} hookName The hook name to check.
  *
  * @return {number}          The number of times the hook has run.
  */
	return function didHook(hookName) {
		return hooks.hasOwnProperty(hookName) && hooks[hookName].runs ? hooks[hookName].runs : 0;
	};
}

exports.default = createDidHook;

/***/ })
/******/ ]);
