export type TabId = 'start' | 'uebersicht' | 'aufgaben' | 'profil'

export type TabDefinition = {
  id: TabId
  label: string
  title: string
  description: string
  points: string[]
}

export const TABS: TabDefinition[] = [
  {
    id: 'start',
    label: 'Start',
    title: 'Willkommen zurück',
    description:
      'Wische horizontal zwischen den Bereichen. Die Seite folgt deinem Finger, der aktive Tab bleibt hervorgehoben.',
    points: [
      'Finger folgt sofort beim Ziehen',
      'Aktiver Tab ist klar markiert',
      'Tippen auf Tabs springt zur Seite',
    ],
  },
  {
    id: 'uebersicht',
    label: 'Übersicht',
    title: 'Tagesüberblick',
    description:
      'Ein kompakter Überblick für Tablet-Nutzung im Quer- und Hochformat.',
    points: ['3 offene Aufgaben', '2 Termine heute', '1 Hinweis zum Sync'],
  },
  {
    id: 'aufgaben',
    label: 'Aufgaben',
    title: 'Deine Aufgaben',
    description: 'Liste der nächsten Schritte – scrollbar innerhalb der Seite.',
    points: [
      'Layout für Tablet prüfen',
      'Swipe-Verhalten auf Gerät testen',
      'Tab-Highlight bei schnellem Wischen prüfen',
    ],
  },
  {
    id: 'profil',
    label: 'Profil',
    title: 'Profil & Gerät',
    description: 'Einstellungen und Gerätehinweise für die Tablet-Oberfläche.',
    points: [
      'Touch-Ziele groß genug',
      'Safe-Area für Notch/Home-Indicator',
      'Offline-fähige Startseite geplant',
    ],
  },
]
