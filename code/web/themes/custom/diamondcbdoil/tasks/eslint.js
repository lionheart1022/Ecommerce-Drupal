module.exports = function(gulp, options) {
  return () => {
    const plumber = require('gulp-plumber');
    const notify = require('gulp-notify');
    const eslint = require('gulp-eslint');
    const onError = err => {
      notify.onError({
        title: 'ESlint Error',
        message:
          'Error: <%= error.message %> in <%= error.fileName %> on <%= error.lineNumber %> line',
        sound: 'Beep'
      })(err);
    };

    return gulp
      .src(options.scripts.src)
      .pipe(
        eslint({
          configFile: './.eslintrc'
        })
      )
      .pipe(plumber())
      .pipe(eslint.format())
      .pipe(eslint.failOnError())
      .on('error', onError);
  };
};
