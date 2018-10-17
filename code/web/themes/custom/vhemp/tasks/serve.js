module.exports = (bs, options) => {
  return () => {
    let onOffFlag = '✗';

    const browserSync = bs.init({
      open: options.browser.autoStart,
      host: options.server.host,
      proxy: options.server.proxy,
      browser: 'default',
      notify: false,
      port: options.server.port
    });

    console.log(`Server started ${onOffFlag}\n`);

    return browserSync;
  };
};
