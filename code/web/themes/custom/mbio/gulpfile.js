const gulp = require('gulp');
const gs = gulp.series;
const gp = gulp.parallel;
const options = require('./options');
const bs = require('browser-sync').create();
const cached = require('gulp-cached');
const remember = require('gulp-remember');
const path = require('path');
const cssnano = require('gulp-cssnano');
const del = require('del');

//Import tasks
const serve = require('./tasks/serve')(bs, options.settings);
const styles = require('./tasks/styles')(gulp, options, bs);
const scripts = require('./tasks/scripts')(gulp, options, bs);
const esLint = require('./tasks/eslint')(gulp, options);
const clean = require('./tasks/clean');
const iconFont = require('./tasks/iconFont')(gulp, options);

// Scripts tasks.
gulp.task('scripts:lint', esLint);
gulp.task('scripts:build', scripts);

// Styles tasks
gulp.task('styles:build', styles);
gulp.task('styles:critical', () => {
  del(
    options.styles.dist +
      '/map/' +
      options.settings.themeName +
      '.critical.css.map'
  );
  return gulp
    .src(
      options.styles.dist + '/' + options.settings.themeName + '.critical.css'
    )
    .pipe(
      cssnano({
        discardComments: {
          removeAll: true
        }
      })
    )
    .pipe(gulp.dest(options.styles.dist));
});

// Compile tasks.
gulp.task('compile:styles', gs('styles:build', 'styles:critical'));
gulp.task('compile:scripts', gs('scripts:lint', 'scripts:build'));
gulp.task('compile', gp('compile:styles', 'compile:scripts'));

// Fonts tasks.
gulp.task(
  'fonts:clean',
  clean(gulp, `${options.font.dir}/${options.font.name}`)
);
gulp.task('fonts:build', iconFont);

// Assets tasks.
gulp.task('assets:fonts', gs('fonts:clean', 'fonts:build'));
gulp.task('assets', gp('assets:fonts'));

// Server task.
gulp.task('server:start', serve);
gulp.task('serve', gs('compile', 'server:start'));

gulp.task('watch:scripts', () => {
  gulp
    .watch(options.scripts.src, gs('compile:scripts'))
    .on('add unlink', function(filepath) {
      remember.forget('scripts', path.resolve(filepath));
      delete cached.caches.scripts[path.resolve(filepath)];
    });
});

gulp.task('watch:styles', () => {
  gulp
    .watch(options.styles.src, gs('compile:styles'))
    .on('add unlink', function(filepath) {
      remember.forget('styles', path.resolve(filepath));
      delete cached.caches.styles[path.resolve(filepath)];
    });
});

gulp.task('watch', gp('watch:scripts', 'watch:styles'));

// Default task.
gulp.task('default', gp('serve', 'watch'));

// Test task.
gulp.task('test', () => {
  console.log('Congratulations! Gulp is working!');
});
