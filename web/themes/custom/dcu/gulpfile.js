let gulp = require('gulp'),
  sass = require('gulp-sass'),
  sourcemaps = require('gulp-sourcemaps'),
  cleanCss = require('gulp-clean-css'),
  rename = require('gulp-rename'),
  postcss = require('gulp-postcss'),
  autoprefixer = require('autoprefixer'),
  browserSync = require('browser-sync').create()

// const paths = {
//   scss: {
//     src: './scss/main.scss',
//     dest: './css',
//     watch: './scss/**/*.scss'
//   }
// }

const paths = {
  scss: {
    src: './scss/main.scss',
    dest: './css',
    watch: './scss/**/*.scss',
    // bootstrap: './node_modules/bootstrap/scss/bootstrap.scss'
  },
  // js: {
  //   bootstrap: './node_modules/bootstrap/dist/js/bootstrap.min.js',
  //   jquery: './node_modules/jquery/dist/jquery.min.js',
  //   popper: 'node_modules/popper.js/dist/umd/popper.min.js',
  //   popper: 'node_modules/popper.js/dist/umd/popper.min.js.map',
  //   dest: './js'
  // }
  js: {
    bootstrap:  './node_modules/bootstrap/dist/js/bootstrap.min.js',
    dest: './js'
  }
}

// Compile sass into CSS & auto-inject into browsers
function styles () {
  //return gulp.src([paths.scss.bootstrap, paths.scss.src])
  return gulp.src([paths.scss.src])
    .pipe(sourcemaps.init())
    .pipe(sass().on('error', sass.logError))
    .pipe(postcss([autoprefixer({
      browsers: [
        'Chrome >= 35',
        'Firefox >= 38',
        'Edge >= 12',
        'Explorer >= 10',
        'iOS >= 8',
        'Safari >= 8',
        'Android 2.3',
        'Android >= 4',
        'Opera >= 12']
    })]))
    .pipe(sourcemaps.write())
    .pipe(gulp.dest(paths.scss.dest))
    .pipe(cleanCss())
    .pipe(rename({ suffix: '.min' }))
    .pipe(gulp.dest(paths.scss.dest))
    .pipe(browserSync.stream())
}

// Move the javascript files into our js folder
function js () {
  return gulp.src([paths.js.bootstrap])
    .pipe(gulp.dest(paths.js.dest))
    .pipe(browserSync.stream())
}

// Static Server + watching scss/html files
function serve () {
  browserSync.init({
    proxy: 'http://dcu-d8:8888/',
  })

  //gulp.watch([paths.scss.watch, paths.scss.bootstrap], styles).on('change', browserSync.reload)
  gulp.watch([paths.scss.watch], styles).on('change', browserSync.reload)
  // gulp.watch(['./scss/extra.scss'], overwritecss).on('change', browserSync.reload)
}

function overwritecss() {
  return gulp.src('./scss/extra.scss')
    .pipe(sourcemaps.init())
    .pipe(sass().on('error', sass.logError))
    .pipe(postcss([autoprefixer({
      browsers: [
        'Chrome >= 35',
        'Firefox >= 38',
        'Edge >= 12',
        'Explorer >= 10',
        'iOS >= 8',
        'Safari >= 8',
        'Android 2.3',
        'Android >= 4',
        'Opera >= 12']
    })]))
    .pipe(sourcemaps.write())
    .pipe(gulp.dest('./css'))
    .pipe(cleanCss())
    .pipe(rename({ suffix: '.min' }))
    .pipe(gulp.dest('./css'))
    .pipe(browserSync.stream())
}

//const build = gulp.series(styles, gulp.parallel(js, serve))
//const build = gulp.series(styles, overwritecss, gulp.parallel(js,serve))
const build = gulp.series(styles, gulp.parallel(js,serve))

exports.styles = styles
// exports.overwritecss = overwritecss
exports.js = js
exports.serve = serve

exports.default = build
