/* eslint-disable no-param-reassign */
/* eslint-disable one-var */
/* eslint-disable one-var-declaration-per-line */
/* eslint linebreak-style: ["error", "windows"] */
/* eslint global-require: "error" */
/* eslint no-param-reassign: "error" */
/* eslint-disable no-restricted-syntax */
/* eslint-disable max-len */

const webpack = require('webpack');
const path = require('path');
const fs = require('fs');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const ReactRefreshWebpackPlugin = require('@pmmmwh/react-refresh-webpack-plugin');
const ESLintPlugin = require('eslint-webpack-plugin');
const StylelintPlugin = require('stylelint-webpack-plugin');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');
const CopyPlugin = require('copy-webpack-plugin');
const DynamicCdnWebpackPlugin = require('@effortlessmotion/dynamic-cdn-webpack-plugin');
const DotEnv = require('dotenv');
const { groupDataByPrefixKey } = require('./webpack_helpers/objects.func');
const { pathDepth, removeDir } = require('./webpack_helpers/path.func');
const Project = require('./project.register');

module.exports = ((env) => {
	let dotEnv, theEnv;
	let alias, plugins, rules, entry;

	if (typeof env.NODE_ENV === 'string' || env.NODE_ENV instanceof String) {
		// Load environment files, for configuration Webpack and Application
		dotEnv = DotEnv.config({ path: `${__dirname}/.env.${(env.NODE_ENV).toLowerCase()}` }).parsed;
		// Transform data by grouping with the same prefix name as the separator prefix '_'
		theEnv = groupDataByPrefixKey(dotEnv, '_');

		// Separate configuration Webpack
		alias = {
			webpack: {
				// Below alias for Path Directory
				Root: path.resolve(__dirname, './src'),
				Apps: path.resolve(__dirname, './src/app'),
				Assets: path.resolve(__dirname, './src/asset'),
				Configs: path.resolve(__dirname, './src/config'),
				Styles: path.resolve(__dirname, './src/app/styles'),
				Components: path.resolve(__dirname, './src/app/components'),
				// Below alias for Depedency Libarary
				jquery: 'jquery/dist/jquery.min.js',
			},
			build: {
				// Below alias for Path Directory
				Root: path.resolve(__dirname, './dist'),
				Apps: path.resolve(__dirname, './dist/app'),
				Assets: path.resolve(__dirname, './dist/assets'),
				Configs: path.resolve(__dirname, './dist/configs'),
				Styles: path.resolve(__dirname, './dist/app/css'),
			},
		};
		plugins = [
			new CleanWebpackPlugin(),
			new webpack.ProvidePlugin({
				$: 'jquery/dist/jquery.min.js',
				jQuery: 'jquery/dist/jquery.min.js',
				moment: 'moment',
				bootstrap: 'bootstrap/dist/js/bootstrap.bundle.min',
				axios: 'axios',
				lottie: 'lottie-web',
				tempusDominus: '@eonasdan/tempus-dominus',
				store: 'store',
				// katex: 'katex/dist/katex.min.js',
				anime: 'animejs',
				Chart: 'chart.js',
				ChartDataLabels: 'chartjs-plugin-datalabels',
				validator: 'validator',
				Scrollbar: 'smooth-scrollbar',
				Values: 'values.js',
			}),
			new webpack.DefinePlugin({
				'process.env.APP': JSON.stringify(theEnv.APP),
			}),
			new webpack.ContextReplacementPlugin(/moment[\\/]locale$/, /^\.\/(en|en-us|ko|ja|zh-cn|id)$/),
			// new webpack.ContextReplacementPlugin(/moment[\\/]locale$/, /en|en-us|ko|ja|zh-cn|id/),
			new ESLintPlugin({
				context: alias.webpack.Apps,
				extensions: ['js', 'jsx'],
				files: '**/*',
				emitError: false,
				emitWarning: false,
				failOnError: false,
				failOnWarning: false,
			}),
			new StylelintPlugin({
				configFile: path.resolve(__dirname, 'stylelint.config.js'),
				context: alias.webpack.Styles,
				extensions: ['css', 'scss', 'sass'],
				files: '**/*',
				emitError: false,
				emitWarning: false,
				failOnError: false,
				failOnWarning: false,
			}),
			new CopyPlugin((() => {
				// Transform specific part of configs, before pass to the plugin
				const copyImportant = [];
				let pushItem = false;
				Project.copyImportant.forEach((item) => {
					if (typeof item === 'object' && item !== null) {
						if (Object.prototype.hasOwnProperty.call(item, 'from')) {
							item.from = path.join(alias.webpack.Root, item.from);
							item.to = path.join(alias.build.Root, (theEnv.WEBPACK.MODE === 'production' || process.env.SERVE === undefined) ? '' : '.development', item.to);
							if (Object.prototype.hasOwnProperty.call(item, 'asset')) {
								const pathTo = item.to;
								if (item.asset) {
									item.to = ({context, absoluteFilename }) => `${pathTo}/[name]-${theEnv.APP.BUILD_VERSION}[ext]`;
									delete item.asset;
								}
							}
							item.noErrorOnMissing = true;
						}
					} else {
						item = path.join(alias.webpack.Root, item);
					}

					if (Object.prototype.hasOwnProperty.call(item, 'from')) {
						pushItem = fs.existsSync(item.from);
					} else {
						pushItem = fs.existsSync(item);
					}

					if (pushItem) { copyImportant.push(item); pushItem = false; }
				});
				return {
					patterns: copyImportant,
				};
			})()),
		];
		rules = [
			/* file loaders: Loader untuk me-resolve file yang dituju dan membuat salinan-nya */
			{
				test: /\.(png|jpe?g|svg|gif|webp)$/i,
				type: 'asset/resource',
				// parser: {
				// 	dataUrlCondition: {
				// 		maxSize: 1024 * theEnv.WEBPACK.ASSET_INLINE_FILESIZE,
				// 	},
				// },
				generator: {
					// filename: `assets/image/[name]-${theEnv.APP.BUILD_VERSION}[ext]`,
					filename: (name) => {
						/**
						 * @description Remove first & last item from ${path} array.
						 * @example
						 *      Orginal Path: 'src/asset/image/logo/logo.jpg'
						 *      Changed To: 'assets/image/logo/logo-[build_version][ext]'
						 */
						let fname = name.filename.split('/').slice(1, -1).join('/');
						fname = fname.replace('asset', 'assets');
						return `${fname}/[name]-${theEnv.APP.BUILD_VERSION}[ext]`;
					},
				},
			},
			{
				test: /\.(woff(2)?|ttf|otf|eot)(\?v=\d+\.\d+\.\d+)?$/,
				type: 'asset/resource',
				// parser: {
				// 	dataUrlCondition: {
				// 		maxSize: 1024*theEnv.WEBPACK.ASSET_INLINE_FILESIZE
				// 	}
				// },
				generator: {
					filename: `assets/font/[name]${(theEnv.WEBPACK.MODE === 'production') ? '-[hash]' : ''}[ext]`,
				},
			},
			{
				test: /\.(ogg|mp3|wav|mpe?g)$/i,
				type: 'asset/resource',
				// parser: {
				// 	dataUrlCondition: {
				// 		maxSize: 1024 * theEnv.WEBPACK.ASSET_INLINE_FILESIZE,
				// 	},
				// },
				generator: {
					// filename: `assets/audio/[name]-${theEnv.APP.BUILD_VERSION}[ext]`,
					filename: (name) => {
						/**
						 * @description Remove first & last item from ${path} array.
						 * @example
						 *      Orginal Path: 'src/asset/audio/audio.wav'
						 *      Changed To: 'assets/audio/audio-[build_version][ext]'
						 */
						let fname = name.filename.split('/').slice(1, -1).join('/');
						fname = fname.replace('asset', 'assets');
						return `${fname}/[name]-${theEnv.APP.BUILD_VERSION}[ext]`;
					},
				},
			},
			{
				test: /\.(mp4|webm|mkv)$/i,
				type: 'asset/resource',
				// parser: {
				// 	dataUrlCondition: {
				// 		maxSize: 1024 * theEnv.WEBPACK.ASSET_INLINE_FILESIZE,
				// 	},
				// },
				generator: {
					// filename: `assets/video/[name]-${theEnv.APP.BUILD_VERSION}[ext]`,
					filename: (name) => {
						/**
						 * @description Remove first & last item from ${path} array.
						 * @example
						 *      Orginal Path: 'src/asset/video/video.mp4'
						 *      Changed To: 'assets/video/video-[build_version][ext]'
						 */
						let fname = name.filename.split('/').slice(1, -1).join('/');
						fname = fname.replace('asset', 'assets');
						return `${fname}/[name]-${theEnv.APP.BUILD_VERSION}[ext]`;
					},
				},
			},
			/* lottie loaders: Loader khusus handle json berkaitan dengan LottieFiles */
			{
				type: 'javascript/auto',
				test: /\.json$/,
				include: /(lottie)/,
				loader: 'lottie-web-webpack-loader',
				options: {
					assets: {
						scale: 0.5, // proportional resizing multiplier
					},
				},
			},
			{
				test: /\.(html|php|hbs)$/i,
				// This solve asset couldn't load in html: https://github.com/pcardune/handlebars-loader/issues/37#issuecomment-365200983
				loader: 'handlebars-loader',
				options: {
					inlineRequires: '/asset/',
					runtime: path.resolve(__dirname, './handlebars.js'),
				},
			},
			/* babel loaders: Convert syntax Modern JS ke dukungan semua browser (Support: ReactJS) */
			{
				test: /\.jsx?$/,
				exclude: /node_modules/,
				// use: [
				// 	'babel-loader',
				// ],
				loader: 'babel-loader',
				options: {
					compact: true,
					// presets: ['@babel/preset-env'],
				},
			},
		];
		entry = {};

		// Dynamically add new entry point to webpack from 'project.register.js'
		for (const [key, value] of Object.entries(Project.jsEntry)) {
			entry[key] = path.join(alias.webpack.Apps, 'js', value);
		}

		/* /
		*****************************************************
		* Configs for NPM run
		* 'build:dev', 'build:raw', 'devel', and 'start'
		*****************************************************
		/ */
		if (process.env.NODE_ENV === 'development') {
			if (process.env.SERVE === true) {
				// Remove 'dist/.development' dir
				removeDir(path.join(alias.build.Root, '.development'));
				// Custom plugins (for specific Environment)
				plugins.push(new ReactRefreshWebpackPlugin()); // for React HotReloadingModule

				// Custom rules (for specific Environment)
				rules.push(
					/* sass, postcss, css, MiniCssExtractPlugin loaders:  < keterangan dibawah > */
					{
						test: /\.(s[ac]|c)ss$/i,
						use: [
							'style-loader',
							'css-loader',
							'postcss-loader',
							'sass-loader', // 1. Compile sass/scss ke css
						],
						sideEffects: true,
					},
				);
			} else {
				// Custom plugins (for specific Environment)
				plugins.push(new MiniCssExtractPlugin({ filename: path.join(path.relative(alias.build.Root, alias.build.Styles), '[name].css').split(path.sep).join(path.posix.sep) }));
				// Custom rules (for specific Environment)
				rules.push(
					/* sass, postcss, css, MiniCssExtractPlugin loaders:  < keterangan dibawah > */
					{
						test: /\.(s[ac]|c)ss$/i,
						use: [
							{
								loader: MiniCssExtractPlugin.loader,
								options: {
									publicPath: ((pathDepth(alias.build.Styles, path.sep) - pathDepth(alias.build.Root, path.sep)) > 0) ? '../'.repeat(pathDepth(alias.build.Styles, path.sep) - pathDepth(alias.build.Root, path.sep)) : './',
								},
							},
							'css-loader',
							'postcss-loader',
							'sass-loader', // 1. Compile sass/scss ke css
						],
						sideEffects: true,
					},
				);
			}
			// Dynamically add new pages website with plugins 'HtmlWebpackPlugin' from 'project.register.js'
			Project.pageList.forEach((page) => {
				plugins.push(new HtmlWebpackPlugin(
					{
						hash: true,
						title: page.title,
						favicon: path.join(alias.webpack.Root, page.favicon),
						template: path.join(alias.webpack.Root, page.template),
						chunks: page.javascript,
						filename: page.output,
						inject: true,
					},
				));
			});
		}

		/* /
		*****************************************************
		* Configs for NPM run
		* 'build'
		*****************************************************
		/ */
		if (process.env.NODE_ENV === 'production') {
			// Custom plugins (for specific Environment)
			plugins.push(new MiniCssExtractPlugin({ filename: path.join(path.relative(alias.build.Root, alias.build.Styles), '[name]-[fullhash].css').split(path.sep).join(path.posix.sep) }));
			plugins.push(new BundleAnalyzerPlugin());
			// Custom rules (for specific Environment)
			rules.push(
				/* sass, postcss, css, MiniCssExtractPlugin loaders:  < keterangan dibawah > */
				{
					test: /\.(s[ac]|c)ss$/i,
					use: [
						{
							loader: MiniCssExtractPlugin.loader,
							options: {
								publicPath: ((pathDepth(alias.build.Styles, path.sep) - pathDepth(alias.build.Root, path.sep)) > 0) ? '../'.repeat(pathDepth(alias.build.Styles, path.sep) - pathDepth(alias.build.Root, path.sep)) : './',
							},
						},
						'css-loader',
						'postcss-loader',
						'sass-loader', // 1. Compile sass/scss ke css
					],
					sideEffects: true,
				},
			);
			// Dynamically add new pages website with plugins 'HtmlWebpackPlugin' from 'project.register.js'
			Project.pageList.forEach((page) => {
				plugins.push(new HtmlWebpackPlugin(
					{
						hash: false,
						title: page.title,
						favicon: path.join(alias.webpack.Root, page.favicon),
						template: path.join(alias.webpack.Root, page.template),
						chunks: page.javascript,
						filename: page.output,
						inject: true,
						minify: {
							html5: false,
							collapseWhitespace: false,
							caseSensitive: false,
							removeComments: true,
						},
					},
				));
			});
		}

		// Dynamic CDN Webpack
		plugins.push(new DynamicCdnWebpackPlugin({
			verbose: true,
			only: [
				// 'jquery',
				'axios',
				'moment',
				'animejs',
				'chart.js',
				'chartjs-plugin-datalabels',
				'lottie-web',
				'store',
				'validator',
				'smooth-scrollbar',
				'values.js',
			],
			// eslint-disable-next-line func-names, object-shorthand
			resolver: function (moduleName, version, options) {
				const mod2Cdn = require('module-to-cdn');
				const mod = mod2Cdn(moduleName, version, options);
				if (mod) return mod;

				const vars = {
					// jquery: '$',
					axios: 'axios',
					moment: 'moment',
					animejs: 'anime',
					'chart.js': 'Chart',
					'chartjs-plugin-datalabels': 'ChartDataLabels',
					'lottie-web': 'lottie',
					store: 'store',
					validator: 'validator',
					'smooth-scrollbar': 'Scrollbar',
					'values.js': 'Values',
				};
				const urls = {
					// jquery: 'https://unpkg.com/jquery@[version]/dist/jquery.min.js',
					axios: 'https://unpkg.com/axios@[version]/dist/axios.min.js',
					moment: 'https://unpkg.com/browse/moment@[version]/min/moment-with-locales.min.js',
					animejs: 'https://unpkg.com/animejs@[version]/lib/anime.min.js',
					'chart.js': 'https://unpkg.com/chart.js@[version]/auto/auto.js',
					'chartjs-plugin-datalabels': 'https://unpkg.com/chartjs-plugin-datalabels@[version]/dist/chartjs-plugin-datalabels.min.js',
					'lottie-web': 'https://www.unpkg.com/lottie-web@[version]/build/player/lottie_light.min.js',
					store: 'https://www.unpkg.com/store@[version]/dist/store.modern.min.js',
					validator: 'https://www.unpkg.com/validator@[version]/validator.min.js',
					'smooth-scrollbar': 'https://unpkg.com/smooth-scrollbar@[version]/dist/smooth-scrollbar.js',
					'values.js': 'https://unpkg.com/values.js@[version]/dist/index.umd.js',
				};

				if (!vars[moduleName]) {
					return null;
				}

				return {
					name: moduleName,
					var: vars[moduleName],
					url: urls[moduleName].replace('[version]', version),
					version,
				};
			},
		}));

		console.log('===========================================');
		console.log(`= Starting Webpack as [${theEnv.WEBPACK.MODE.toUpperCase()}] mode! =`);
		console.log('===========================================');
	}

	// Webpack configs
	return {
		mode: theEnv.WEBPACK.MODE,
		target: theEnv.WEBPACK.MODE === 'production' ? 'browserslist' : 'web',
		entry,
		output: {
			path: path.resolve(__dirname, (theEnv.WEBPACK.MODE === 'production' || process.env.SERVE === undefined) ? 'dist' : 'dist/.development'),
			filename: (pathData) => {
				const { name } = pathData.chunk;
				let contentHash = '';
				if (pathData.chunk.hash !== undefined) {
					contentHash = `-${pathData.chunk.contentHash.javascript}`;
				}
				const isVendor = ['runtime', 'react', 'bundle', 'vendor', 'common'].includes(name);
				const prefix = (isVendor) ? 'chunks' : 'bundle';
				const vendorDir = (isVendor) ? 'vendor/' : '';

				return `./app/js/${vendorDir}${name}${contentHash}.${prefix}.js`;
			},
			chunkFilename: `./app/js/module/[name]${(theEnv.WEBPACK.MODE === 'production') ? '-[fullhash]' : ''}.chunk.js`,
			hotUpdateChunkFilename: `./app/js/[name]${(theEnv.WEBPACK.MODE === 'production') ? '-[fullhash]' : ''}.hot-update.js`,
			assetModuleFilename: `assets/[name]${(theEnv.WEBPACK.MODE === 'production') ? '-[fullhash]' : ''}[ext][query]`,
			clean: true,
			// publicPath: 'http://apps.mtp-logistics.com:8181/kpi/',
			// publicPath: 'http://101.255.157.147/inno-dashboard/',
			// publicPath: 'http://localhost/mtp/mtp-kpi/dist/.development/',
		},
		optimization: {
			runtimeChunk: 'single',
			splitChunks: {
				chunks: 'all',
				maxInitialRequests: Infinity,
				minSize: 0,
				cacheGroups: {
					default: false,
					vendors: false,
					// Vendor chunk yang dipisahkan berdasarkan nama package dia sendiri
					// vendor: {
					// 	test: /[\\/]node_modules[\\/]/,
					// 	name(module) {
					// 		const packageName = module.context.match(/[\\/]node_modules[\\/](.*?)([\\/]|$)/)[1];
					// 		return `npm.${packageName.replace('@', '')}`;
					// 	}
					// },
					// React Vendor chunk
					react: {
						name: 'react',
						chunks: 'all',
						test: /[\\/]node_modules[\\/]((react).*)[\\/]/,
						priority: 20,
					},
					// React Vendor chunk
					zxcvbn: {
						name: 'zxcvbn',
						chunks: 'all',
						test: /[\\/]node_modules[\\/]((zxcvbn).*)[\\/]/,
						priority: 20,
					},
					// Amcharts4 Vendor chunk
					amcharts4: {
						name: 'amcharts4',
						chunks: 'all',
						test: /[\\/]node_modules[\\/]((@amcharts).*)[\\/]/,
						priority: 20,
					},
					// All Vendor chunk
					bundle: {
						name: 'bundle',
						chunks: 'all',
						test: /[\\/]node_modules[\\/]((?!react|zxcvbn|@amcharts).*)[\\/]/,
						priority: 20,
					},
					// Common chunk
					common: {
						name: 'common',
						minChunks: 2,
						chunks: 'async',
						priority: 10,
						reuseExistingChunk: true,
						enforce: true,
					},
				},
			},
		},
		module: {
			rules,
		},
		plugins,
		resolve: {
			extensions: ['.js', '.jsx'],
			modules: ['node_modules'],
			alias: alias.webpack,
			fallback: {
				crypto: false,
			},
		},
		devtool: theEnv.WEBPACK.MODE === 'production' ? false : 'source-map',
		devServer: {
			open: true,
			hot: true,
			allowedHosts: ['localhost'],
			host: theEnv.WEBPACK.HOST_LOCAL,
			port: `${theEnv.WEBPACK.HOST_PORT}`,
			compress: true,
			historyApiFallback: true,
			proxy: {
				// Star(*) defines all the valid requests
				'*': {
					// Specifying the full path to the dist folder
					target: `http://${theEnv.WEBPACK.HOST_LOCAL}${theEnv.WEBPACK.HOST_PROJECT_DIR}/dist/.development`,
					secure: false,
					changeOrigin: true,
				},
			},
			static: path.resolve(__dirname, alias.build.Root, '.development'),
			// compress: true,
			// It writes generated assets to the dist folder
			devMiddleware: {
				writeToDisk: true,
			},
		},
		// eslint-disable-next-line func-names, object-shorthand
		externals: function (context, request, callback) {
			if (/xlsx|canvg|pdfmake/.test(request)) {
				return callback(null, `commonjs ${request}`);
			}
			return callback();
		},
	};
})(process.env);
