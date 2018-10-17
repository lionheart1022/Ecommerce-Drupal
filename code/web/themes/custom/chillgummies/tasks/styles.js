module.exports = (gulp, options, bs) => {
  return () => {
    const sass = require('gulp-sass');
    const sourceMaps = require('gulp-sourcemaps');
    const plumber = require('gulp-plumber');
    const autoPrefixer = require('gulp-autoprefixer');
    const notify = require('gulp-notify');
    const rename = require('gulp-rename');
    const gutil = require('gulp-util');
    const cache = require('gulp-cached');
    const remember = require('gulp-remember');
    const dependents = require('gulp-dependents');
    const aliases = require('gulp-style-aliases');
    const postcss = require('gulp-postcss');
    const sortCssMq = require('sort-css-media-queries');
    const postcssPlugins = [require('css-mqpacker')({ sort: sortCssMq })];
    const criticalCss = require('gulp-critical-css');

    return (
      gulp
        .src(options.styles.src)
        .pipe(
          aliases({
            '@cpl_base': options.aliases.cplBase + '/scss',
            '@cpl_commerce': options.aliases.cplBaseCommerce + '/scss'
          })
        )
        // TODO: aliases work incorrectly with the caching and dependents enabled
        .pipe(options.styles.cache ? cache('styles') : gutil.noop())
        // .pipe(dependents())
        .pipe(options.styles.sourceMap ? sourceMaps.init() : gutil.noop())
        .pipe(
          plumber({
            errorHandler: notify.onError('Error: <%= error.message %>')
          })
        )
        .pipe(
          options.styles.compressed
            ? sass({ outputStyle: 'compressed' })
            : sass()
        )
        .pipe(
          options.styles.autoprefixer
            ? autoPrefixer({
                browsers: ['> 0.25%', 'ie >=11', 'not op_mini all'],
                cascade: false
              })
            : gutil.noop()
        )
        .pipe(options.styles.cache ? remember('styles') : gutil.noop())
        .pipe(
          aliases({
            '@cpl_base': options.aliases.cplBase,
            '@cpl_commerce': options.aliases.cplBaseCommerce
          })
        )
        .pipe(postcss(postcssPlugins))
        .pipe(criticalCss())
        .pipe(options.styles.sourceMap ? sourceMaps.write('map') : gutil.noop())
        .pipe(gulp.dest(options.styles.dist))
        .pipe(
          options.settings.browser.autoReload
            ? bs.reload({ stream: true })
            : gutil.noop()
        )
    );
  };
};
