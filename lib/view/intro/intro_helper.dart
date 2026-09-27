class IntroHelper {
  getImage(int i) {
    return 'assets/images/intro${i + 1}.png';
  }

  geTitle(int i) {
    List title = [
      "Discover Your Perfect Moment",
      "Everything for Your Event",
      "Choose. Book. Celebrate."
    ];
    return title[i];
  }

  geSubTitle(int i) {
    List subTitle = [
      "Find DJs, entertainment, venues, and everything you need to bring your event to life.",
      "From equipment and décor to catering and more, find it all in one place.",
      "Compare services, book with ease, and enjoy the moment you created."
    ];
    return subTitle[i];
  }
}
