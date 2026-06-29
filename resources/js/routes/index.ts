import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../wayfinder'

/* ---------------- LOGIN ---------------- */

export const login = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

login.definition = {
    methods: ["get", "head"],
    url: '/login',
} satisfies RouteDefinition<["get", "head"]>

login.url = (options?: RouteQueryOptions) =>
    login.definition.url + queryParams(options)

login.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

login.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: login.url(options),
    method: 'head',
})

const loginForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: login.url(options),
    method: 'get',
})

login.form = loginForm

/* ---------------- LOGOUT ---------------- */

export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

logout.definition = {
    methods: ["post"],
    url: '/logout',
} satisfies RouteDefinition<["post"]>

logout.url = (options?: RouteQueryOptions) =>
    logout.definition.url + queryParams(options)

logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

const logoutForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: logout.url(options),
    method: 'post',
})

logout.form = logoutForm

/* ---------------- REGISTER ---------------- */

export const register = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: register.url(options),
    method: 'get',
})

register.definition = {
    methods: ["get", "head"],
    url: '/register',
} satisfies RouteDefinition<["get", "head"]>

register.url = (options?: RouteQueryOptions) =>
    register.definition.url + queryParams(options)

register.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: register.url(options),
    method: 'get',
})

register.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: register.url(options),
    method: 'head',
})

const registerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: register.url(options),
    method: 'get',
})

register.form = registerForm

/* ---------------- HOME ---------------- */

export const home = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

home.definition = {
    methods: ["get", "head"],
    url: '/',
} satisfies RouteDefinition<["get", "head"]>

home.url = (options?: RouteQueryOptions) =>
    home.definition.url + queryParams(options)

home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
})

const homeForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: home.url(options),
    method: 'get',
})

home.form = homeForm

/* ---------------- CHAT ---------------- */

export const chat = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: chat.url(options),
    method: 'get',
})

chat.definition = {
    methods: ["get", "head"],
    url: '/chat',
} satisfies RouteDefinition<["get", "head"]>

chat.url = (options?: RouteQueryOptions) =>
    chat.definition.url + queryParams(options)

chat.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: chat.url(options),
    method: 'get',
})

chat.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: chat.url(options),
    method: 'head',
})

const chatForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: chat.url(options),
    method: 'get',
})

chat.form = chatForm

/* ---------------- FIX IMPORTANT ---------------- */
/* ton erreur dashboard */

export const dashboard = chat