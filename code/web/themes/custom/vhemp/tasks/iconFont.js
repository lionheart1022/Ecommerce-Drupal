module.exports = (gulp, options) => {
  return () => {
    const iconFont = require('gulp-iconfont');
    const iconFontStyles = require('gulp-iconfont-css');

    return gulp.src([`${options.images.icons}/*.svg`])
        .pipe(iconFontStyles({
          fontName: options.font.name,
          path: 'icon-font-template.scss',
          targetPath: `${options.font.stylePath}/_${options.font.stylesName}.scss`,
          fontPath: `${options.font.styleFontPath}/${options.font.name}/`,
          cssClass: `${options.font.name}`
        }))
        .pipe(iconFont({
          fontName: options.font.name,
          prependUnicode: true,
          formats: ['ttf', 'eot', 'woff', 'svg', 'woff2']
        }))
        .pipe(gulp.dest(`${options.font.dir}/${options.font.name}`));
  };
};