class IntroHelper {
  getImage(int i) {
    return 'assets/images/intro${i + 1}.png';
  }

  geTitle(int i) {
    List title = [
      "Creative Hub",
      "Event Rentals",
      "Seamless Artistic Communication"
    ];
    return title[i];
  }

  geSubTitle(int i) {
    List subTitle = [
      "Connecting Art and Entertainment Experts",
      "Discover Creative Solutions in One Touch",
      "Bridging Artists and Creative Projects"
    ];
    return subTitle[i];
  }
}
