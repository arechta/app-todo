const plugins = [
	"@babel/plugin-transform-runtime",
	"@babel/plugin-proposal-class-properties",
	"@babel/plugin-proposal-private-methods",
	"@babel/plugin-syntax-dynamic-import",
];

if (process.env.NODE_ENV === "development" && process.env.SERVE == true) {
    plugins.push('react-refresh/babel');
}

module.exports = {
    presets: [
        '@babel/preset-env',
        ['@babel/preset-react', { runtime: 'automatic' } ],
    ],
    plugins: plugins
}
