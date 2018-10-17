module.exports = (gulp, folder) => {
  return () => {
    const clean = require('gulp-clean');
    return gulp.src(folder, {
      read: false
    }).pipe(clean({
      force: true
    }));
  };
};