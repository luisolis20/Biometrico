const { defineConfig } = require("@vue/cli-service");
const webpack = require("webpack");

module.exports = defineConfig({
  transpileDependencies: true,

  // ✅ Usa ruta raíz en producción (evita revelar nombre de carpeta)
  publicPath: process.env.NODE_ENV === "production" ? "/biometricoutlvte/" : "/",

  // ✅ Carpeta limpia para build
  outputDir: "dist",
  assetsDir: "assets",

  productionSourceMap: false, // ❌ Evita que el navegador muestre código fuente (map files)

  configureWebpack: {
    optimization: {
      minimize: true, // ✅ Minifica todo el código JS y CSS
      splitChunks: {
        chunks: "all",
      },
    },
    plugins: [
      new webpack.ProvidePlugin({
        $: "jquery",
        jQuery: "jquery",
        "window.jQuery": "jquery",
      }),
      // ✅ Define variables globales seguras para tus URLs
      new webpack.DefinePlugin({
        __API_BIO__: JSON.stringify(
          process.env.NODE_ENV === "production"
            ? "http://biometricobackend.test/api"
            : "http://biometricobackend.test/api"
        ),
      }),
    ],
    output: {
      // ✅ Archivos con hash aleatorio (ocultan nombres)
      filename: "assets/js/[name].[contenthash].js",
      chunkFilename: "assets/js/[name].[contenthash].js",
    },
  },

  devServer: {
    proxy: {
      "/api": {
        target: "http://biometricobackend.test",
        changeOrigin: true,
        pathRewrite: { "^/api": "" },
      },
    },
  },
});
