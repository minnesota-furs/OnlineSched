const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

const autoprefixer = require('autoprefixer')

module.exports = {

    entry: {
        main: './src/index.js',
        'admin-badge-types': './src/scss/admin-badge-types.scss',
        'hours-blocks': './src/js/hoursBlocks.js',
        fontawesome: './src/scss/fontawesome.scss',
        fonts: './src/scss/fonts.scss',
    },

    output: {
        path: path.resolve(__dirname, 'build'),
        filename: (pathData) => {
            return pathData.chunk.name === 'main' ? 'bundle.js' : '[name].bundle.js';
        }
    },

    performance: {
        assetFilter: (assetFilename) => !/\.(?:woff2?|ttf|eot|svg)$/i.test(assetFilename),
    },

    devtool: 'source-map',

    resolve: {
        extensions: ['.js', '.jsx', '.css', '.scss', '.sass'],
        modules: [
            path.resolve(__dirname, 'src'),
            'node_modules'
        ]
    },

    module: {
        rules: [
            {
                test: /\.jsx?$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader'
                }
            },
            {
                test: /\.css$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    {
                        // Loader for webpack to process CSS with PostCSS
                        loader: 'postcss-loader',
                        options: {
                            postcssOptions: {
                                plugins: [
                                    autoprefixer
                                ]
                            }
                        }
                    }
                ]
            },
            {
                test: /\.s[ac]ss$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader',
                    {
                        // postcss runs on compiled CSS (after sass)
                        loader: 'postcss-loader',
                        options: {
                            postcssOptions: {
                                plugins: [
                                    autoprefixer
                                ]
                            }
                        }
                    },
                    {
                        // sass compiles SCSS -> CSS first
                        loader: 'sass-loader',
                        options: {
                            api: "modern",
                        }
                    }
                ]
            }
        ]
    },

    plugins: [
        new MiniCssExtractPlugin({
            filename: '[name].css',
            chunkFilename: '[id].css',
        }),
    ]

};
