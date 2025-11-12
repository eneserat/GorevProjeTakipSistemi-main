# Görev / Proje Takip Sistemi

Görev / Proje Takip Sistemi, ekiplerin projelerini, görevlerini ve çalışma süreçlerini kolayca yönetebilmeleri için geliştirilmiş web tabanlı bir uygulamadır. Bu sistem, proje yöneticileri, geliştiriciler, tasarımcılar ve diğer ekip üyeleri için güçlü bir araç seti sunar.

---

## Öne Çıkan Özellikler

- **Kullanıcı Yönetimi**
  - Admin ve normal kullanıcı rolleri
  - Kullanıcı profilleri ve profil fotoğrafı yükleme
- **Proje Yönetimi**
  - Proje oluşturma, düzenleme ve silme
  - Proje öncelik ve durum yönetimi (low, medium, high / planning, in_progress, on_hold, completed)
  - Projeye kullanıcı atama ve rol yönetimi (owner, dev, qa, pm, designer, analyst)
- **Görev Yönetimi**
  - Görev oluşturma, atama ve durum takibi (todo, in_progress, on_hold, completed)
  - Görev öncelik seviyesi belirleme
  - Görev yorumları ekleme ve görüntüleme
  - Dosya yükleme ve paylaşımı
- **Pomodoro Zamanlayıcı**
  - Focus, kısa ve uzun mola seansları
  - Seans süresi takibi ve geçmiş kayıtlar
- **Bildirim Sistemi**
  - Yeni görev veya proje atamalarında kullanıcıya bildirim gönderme
- **Mesajlaşma / Chat**
  - Kullanıcılar arasında özel mesajlaşma
  - Dosya paylaşımı ve mesaj durumu takibi (sent, delivered, seen)
- **Favoriler**
  - Kullanıcıların önemli ürün veya projeleri favorilere ekleyebilmesi

---

## Kullanılan Teknolojiler

- **Backend:** PHP 8.x, PDO ile güvenli veritabanı işlemleri  
- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5  
- **Veritabanı:** MySQL / MariaDB  
- **Sunucu:** XAMPP / Localhost  

---

## Kurulum Adımları

1. Projeyi yerel bilgisayarınıza klonlayın:

```bash
git clone https://github.com/kullaniciadi/GorevProjeTakipSistemi.git


Kullanım Senaryoları

Admin Kullanıcı:

Tüm projeleri ve görevleri görebilir ve yönetebilir.

Kullanıcı ekleyebilir, düzenleyebilir ve roller atayabilir.

Bildirimleri ve pomodoro seanslarını takip edebilir.

Normal Kullanıcı:

Kendisine atanan projeleri ve görevleri görebilir.

Görev durumlarını güncelleyebilir, yorum ekleyebilir ve dosya paylaşabilir.

Pomodoro seanslarını kullanarak iş takibi yapabilir.

Katkıda Bulunma

Projeye katkıda bulunmak için:

Repository'i fork'layın.

Yeni bir branch oluşturun (git checkout -b feature/ozellik).

Değişikliklerinizi commit edin (git commit -m 'Yeni özellik eklendi').

Branch'i push edin (git push origin feature/ozellik).

Pull request gönderin.

Her türlü hata bildirimi veya öneri için issue açabilirsiniz.
