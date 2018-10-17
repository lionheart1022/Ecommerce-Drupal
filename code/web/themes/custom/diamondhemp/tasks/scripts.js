module.exports = (gulp, options, bs) => {
  return () => {
    const babel = require('gulp-babel');
    const sourceMaps = require('gulp-sourcemaps');
    const plumber = require('gulp-plumber');
    const notify = require('gulp-notify');
    const concat = require('gulp-concat');
    const gutil = require('gulp-util');
    const cache = require('gulp-cached');
    const remember = require('gulp-remember');
    const wrapJS = require('gulp-wrap-js');
    const through2 = require('through2').obj;
    const path = require('path');
    const uglify = require('gulp-uglify');
    const fileName = (file, enc, callback) => {
      const ext = path.extname(file.relative);
      const name = path.basename(file.relative, ext);

      if (ext !== '.noRename') {
        file.name =
          options.settings.themeName +
          name.charAt(0).toUpperCase() +
          name.slice(1);
      } else {
        const newExt = path.extname(name);
        file.name = path.basename(name, newExt);
      }

      callback(null, file);
    };

    return gulp
      .src([options.scripts.src, options.scripts.noRename])
      .pipe(options.scripts.sourceMap ? sourceMaps.init() : gutil.noop())
      .pipe(
        plumber({
          errorHandler: notify.onError('Scrips Error: <%= error.message %>')
        })
      )
      .pipe(
        through2(function(file, enc, callback) {
          fileName(file, enc, callback);
        })
      )
      .pipe(
        wrapJS(
          'Drupal.behaviors["%= file.name %"] = { attach: function attach(context, settings) { %= body % }};'
        )
      )
      .pipe(concat(`${options.settings.themeName}.js`))
      .pipe(
        wrapJS(
          '"use strict"; (function($, Drupal) {%= body %})(jQuery, Drupal);'
        )
      )
      .pipe(options.scripts.cache ? cache('scripts') : gutil.noop())
      .pipe(
        babel({
          presets: ['babel-preset-es2015'].map(require.resolve)
        })
      )
      .pipe(options.scripts.cache ? remember('scripts') : gutil.noop())
      .pipe(options.scripts.sourceMap ? sourceMaps.write('map') : gutil.noop())
      .pipe(options.scripts.compressed ? uglify() : gutil.noop())
      .pipe(gulp.dest(options.scripts.dist))
      .pipe(
        options.settings.browser.autoReload
          ? bs.reload({ stream: true })
          : gutil.noop()
      );
  };
};
