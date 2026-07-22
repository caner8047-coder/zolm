(function (global) {
  function target(value) {
    let query = String(value || '').replace(/\s+/g, ' ').trim();
    if (query.length < 2) {
      throw new Error('En az 2 karakterli barkod, ürün kodu veya bağlantı girin.');
    }

    if (/^(?:www\.)?trendyol\.com\//i.test(query) || /^ty\.gl\//i.test(query)) {
      query = 'https://' + query;
    }

    if (/^https?:\/\//i.test(query)) {
      let url;
      try {
        url = new URL(query);
      } catch (error) {
        throw new Error('Geçerli bir Trendyol bağlantısı girin.');
      }

      const host = url.hostname.toLocaleLowerCase('tr-TR');
      if (!(host === 'trendyol.com' || host.endsWith('.trendyol.com') || host === 'ty.gl' || host.endsWith('.ty.gl'))) {
        throw new Error('Yalnız Trendyol veya ty.gl bağlantıları açılabilir.');
      }

      return {
        url: url.href,
        label: /-p-\d+/i.test(url.pathname) ? 'Ürün bağlantısı' : 'Trendyol bağlantısı',
      };
    }

    query = query.slice(0, 180);
    const searchUrl = new URL('https://www.trendyol.com/sr');
    searchUrl.searchParams.set('q', query);
    searchUrl.searchParams.set('qt', query);
    searchUrl.searchParams.set('st', query);
    searchUrl.searchParams.set('os', '1');

    return {
      url: searchUrl.href,
      label: /^\d{8,14}$/.test(query) ? 'Barkod araması' : (/^\d+$/.test(query) ? 'Ürün kodu araması' : 'Ürün araması'),
    };
  }

  global.ZolmDiscovery = Object.freeze({ target });
})(globalThis);
