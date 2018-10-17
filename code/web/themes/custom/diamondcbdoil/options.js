/*
 * !!!! IMPORTANT !!!!
 * Please change this two lines according
 * to you theme name and local site url.
 */
let themeName = 'diamondcbdoil';
const proxy = 'http://DOMAIN.docker.localhost/';

const dirname = __dirname;
const themeArray = dirname.split('/');
const possiblyThemeName = themeArray[themeArray.length - 1];

function getBaseThemeFolder() {
  var fs = require('fs');
  var path = require('path');
  var variants = [
    'cpl_base_theme/cpl_base/cpl_base.info.yml',
    'cpl_base/cpl_base.info.yml',
    'contrib/cpl_base_theme/cpl_base/cpl_base.info.yml',
    'contrib/cpl_base/cpl_base.info.yml'
  ];
  var possiblePath;

  var dirname = '';

  while (path.normalize(dirname) !== '/') {
    for (var i in variants) {
      possiblePath = dirname + variants[i];
      if (fs.existsSync(possiblePath)) {
        return path.dirname(path.dirname(possiblePath));
      }
    }
    dirname += '../';
  }
}

const baseThemeFolder = getBaseThemeFolder(dirname);

// Reassign theme name to possible theme name.
themeName = themeName ? themeName : possiblyThemeName;

module.exports = {
  aliases: {
    cplBase: `${baseThemeFolder}/cpl_base`,
    cplBaseCommerce: `${baseThemeFolder}/cpl_base_commerce`
  },
  scripts: {
    dist: `${dirname}/js`,
    src: `${dirname}/js/behaviors/**/*.js`,
    noRename: `${dirname}/js/behaviors/**/*.noRename`,
    dir: `${dirname}/js/behaviors`,
    sourceMap: true,
    cache: false,
    compressed: false
  },
  styles: {
    dist: `${dirname}/css`,
    src: [
      `${dirname}/scss/**/*.scss`,
      `${baseThemeFolder}/cpl_base/scss/**/*.scss`,
      `${baseThemeFolder}/cpl_base_commerce/scss/**/*.scss`
    ],
    dir: `${dirname}/scss`,
    sourceMap: true,
    cache: false,
    compressed: false,
    autoprefixer: true
  },
  font: {
    dir: `${dirname}/fonts`,
    name: `${themeName}-font`,
    stylesName: 'icon-font',
    stylePath: '../../scss/base',
    styleFontPath: '../../fonts'
  },
  images: {
    dir: `${dirname}/images`,
    icons: `${dirname}/images/icons`
  },
  settings: {
    themeName: themeName,
    browser: {
      autoStart: false,
      autoReload: true
    },
    server: {
      port: 3000,
      host: 'localhost',
      proxy: proxy ? proxy : 'localhost'
    }
  }
};
